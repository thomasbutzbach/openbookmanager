<?php
/**
 * Import Scanned Book - Edit and categorize before importing
 */

$app = require __DIR__ . '/../../../src/bootstrap.php';
extract($app);

requireAuth();

$errors = [];
$scannedBook = null;
$parsedAuthors = [];

// Get scanned book ID
$scannedBookId = $_GET['id'] ?? null;

if (!$scannedBookId) {
    setFlash('error', 'No book specified.');
    redirect('/books/import/');
}

// Load scanned book
try {
    $stmt = $db->prepare('SELECT * FROM scanned_books WHERE id = ?');
    $stmt->execute([$scannedBookId]);
    $scannedBook = $stmt->fetch();

    if (!$scannedBook) {
        setFlash('error', 'Scanned book not found.');
        redirect('/books/import/');
    }

    // Check if already imported
    if ($scannedBook['status'] === 'imported') {
        setFlash('warning', 'This book has already been imported.');
        redirect('/books/view.php?id=' . $scannedBook['imported_book_id']);
    }

    // Parse authors from raw data
    if ($scannedBook['authors_raw']) {
        $parsedAuthors = parseAndMatchAuthors($db, $scannedBook['authors_raw']);
    }

} catch (PDOException $e) {
    setFlash('error', 'Database error: ' . $e->getMessage());
    redirect('/books/import/');
}

// Initialize form data
$formData = [
    'title' => $scannedBook['title'] ?? '',
    'subtitle' => $scannedBook['subtitle'] ?? '',
    'year' => $scannedBook['published_year'] ?? null,
    'pages' => $scannedBook['pages'] ?? null,
    'isbn' => $scannedBook['isbn'] ?? '',
    'publisher' => $scannedBook['publisher'] ?? '',
    'language' => $scannedBook['language'] ?? '',
    'code_category' => '',
    'authors' => $parsedAuthors
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $formData = [
        'title' => trim($_POST['title'] ?? ''),
        'subtitle' => trim($_POST['subtitle'] ?? ''),
        'year' => !empty($_POST['year']) ? (int)$_POST['year'] : null,
        'pages' => !empty($_POST['pages']) ? (int)$_POST['pages'] : null,
        'isbn' => trim($_POST['isbn'] ?? ''),
        'publisher' => trim($_POST['publisher'] ?? ''),
        'language' => trim($_POST['language'] ?? ''),
        'code_category' => trim($_POST['code_category'] ?? ''),
        'author_ids' => $_POST['author_ids'] ?? [],
        'new_authors' => $_POST['new_authors'] ?? [] // Format: ["surname|lastname", ...]
    ];

    // Validation
    if (empty($formData['title'])) {
        $errors[] = 'Title is required.';
    }

    if (empty($formData['code_category'])) {
        $errors[] = 'Category is required.';
    }

    if (empty($formData['author_ids']) && empty($formData['new_authors'])) {
        $errors[] = 'At least one author is required.';
    }

    // If no errors, import book
    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Get next number for this category with row lock
            $stmt = $db->prepare('
                SELECT next_number
                FROM category_sequences
                WHERE code_category = ?
                FOR UPDATE
            ');
            $stmt->execute([$formData['code_category']]);
            $sequence = $stmt->fetch();

            if (!$sequence) {
                throw new Exception('Category sequence not found');
            }

            $numberInCategory = $sequence['next_number'];

            // Insert book
            $stmt = $db->prepare('
                INSERT INTO books (title, subtitle, year, pages, isbn, cover_image, code_category, number_in_category, publisher, language, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $formData['title'],
                $formData['subtitle'] ?: null,
                $formData['year'],
                $formData['pages'],
                $formData['isbn'] ?: null,
                $scannedBook['cover_local'] ?: null,
                $formData['code_category'],
                $numberInCategory,
                $formData['publisher'] ?: null,
                $formData['language'] ?: null,
                null // notes
            ]);

            $bookId = $db->lastInsertId();

            // Handle authors
            $allAuthorIds = [];

            // Add existing authors
            foreach ($formData['author_ids'] as $authorId) {
                if (!empty($authorId)) {
                    $allAuthorIds[] = (int)$authorId;
                }
            }

            // Create new authors
            $stmt = $db->prepare('INSERT INTO authors (surname, lastname) VALUES (?, ?)');
            foreach ($formData['new_authors'] as $newAuthor) {
                if (!empty($newAuthor)) {
                    $parts = explode('|', $newAuthor, 2);
                    if (count($parts) === 2) {
                        $stmt->execute([trim($parts[0]), trim($parts[1])]);
                        $allAuthorIds[] = $db->lastInsertId();
                    }
                }
            }

            // Link authors to book
            $stmt = $db->prepare('INSERT INTO book_author (book_id, author_id) VALUES (?, ?)');
            foreach ($allAuthorIds as $authorId) {
                $stmt->execute([$bookId, $authorId]);
            }

            // Update category sequence
            $stmt = $db->prepare('
                UPDATE category_sequences
                SET next_number = next_number + 1
                WHERE code_category = ?
            ');
            $stmt->execute([$formData['code_category']]);

            // Delete scanned book after successful import
            // All relevant data is now in the books table, no need to keep the staging data
            $stmt = $db->prepare('DELETE FROM scanned_books WHERE id = ?');
            $stmt->execute([$scannedBookId]);

            $db->commit();

            // Flash message with link to the imported book
            $bookTitle = htmlspecialchars($formData['title'], ENT_QUOTES, 'UTF-8');
            $viewLink = '/books/view.php?id=' . $bookId;
            setFlash('success', "Book '{$bookTitle}' imported successfully! <a href='{$viewLink}' style='color: #1e293b; font-weight: 600; text-decoration: underline;'>View details →</a>", true);

            // Redirect back to import manager for batch processing
            redirect('/books/import/');

        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Import failed: ' . $e->getMessage();
        }
    }

    // Re-parse authors for display if there are errors
    if (!empty($errors) && !empty($formData['author_ids'])) {
        $parsedAuthors = [];
        foreach ($formData['author_ids'] as $authorId) {
            if (!empty($authorId)) {
                $stmt = $db->prepare('SELECT * FROM authors WHERE id = ?');
                $stmt->execute([$authorId]);
                if ($author = $stmt->fetch()) {
                    $parsedAuthors[] = [
                        'existing_id' => $author['id'],
                        'surname' => $author['surname'],
                        'lastname' => $author['lastname'],
                        'is_new' => false
                    ];
                }
            }
        }
    }
}

// Get categories grouped by main category
try {
    $stmt = $db->query('
        SELECT c.*, m.code as maincat_code, m.title as maincat_title
        FROM categories c
        JOIN maincategories m ON c.code_maincategory = m.code
        ORDER BY m.code, c.code
    ');
    $categories = $stmt->fetchAll();

    $categoriesByMain = [];
    foreach ($categories as $cat) {
        $mainCode = $cat['maincat_code'];
        if (!isset($categoriesByMain[$mainCode])) {
            $categoriesByMain[$mainCode] = [
                'code' => $mainCode,
                'title' => $cat['maincat_title'],
                'subcategories' => []
            ];
        }
        $categoriesByMain[$mainCode]['subcategories'][] = $cat;
    }

} catch (PDOException $e) {
    $errors[] = 'Failed to load categories: ' . $e->getMessage();
}

// Get all authors for selection
try {
    $authorsStmt = $db->query('SELECT * FROM authors ORDER BY lastname, surname');
    $allAuthors = $authorsStmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = 'Failed to load authors: ' . $e->getMessage();
}

$pageTitle = 'Import Book: ' . e($scannedBook['title']);
include __DIR__ . '/../../../src/Views/layout/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>📥 Import Book</h1>
        <a href="/books/import/" class="btn btn-secondary">← Back to Import Manager</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Please fix the following errors:</strong>
            <ul style="margin: 0.5rem 0 0 0; padding-left: 1.5rem;">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h2 style="margin: 0;">📚 Book Information</h2>
            </div>
            <div class="card-body">
                <div style="display: flex; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <!-- Cover Preview -->
                    <div style="flex-shrink: 0;">
                        <div style="width: 150px; height: 225px; overflow: hidden; border-radius: 0.375rem; background: #e9ecef; border: 2px solid var(--border-color);">
                            <img
                                src="<?= e($scannedBook['cover_local'] ?: $scannedBook['cover_url'] ?: '/images/no-cover.svg') ?>"
                                alt="Book cover"
                                style="width: 100%; height: 100%; object-fit: cover;"
                                onerror="this.src='/images/no-cover.svg'"
                            >
                        </div>
                        <?php if ($scannedBook['cover_local'] || $scannedBook['cover_url']): ?>
                            <p style="margin: 0.5rem 0 0 0; font-size: 0.75rem; color: var(--secondary-color); text-align: center;">
                                Cover from <?= $scannedBook['cover_local'] ? 'download' : 'URL' ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Form Fields -->
                    <div style="flex: 1;">
                        <div class="form-group">
                            <label for="title">Title *</label>
                            <input type="text" id="title" name="title" value="<?= e($formData['title']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="subtitle">Subtitle</label>
                            <input type="text" id="subtitle" name="subtitle" value="<?= e($formData['subtitle']) ?>">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="isbn">ISBN</label>
                                <input type="text" id="isbn" name="isbn" value="<?= e($formData['isbn']) ?>" readonly style="background: var(--bg-secondary);">
                            </div>

                            <div class="form-group">
                                <label for="year">Year</label>
                                <input type="number" id="year" name="year" value="<?= e($formData['year']) ?>" min="1000" max="2100">
                            </div>

                            <div class="form-group">
                                <label for="pages">Pages</label>
                                <input type="number" id="pages" name="pages" value="<?= e($formData['pages']) ?>" min="1">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="publisher">Publisher</label>
                                <input type="text" id="publisher" name="publisher" value="<?= e($formData['publisher']) ?>">
                            </div>

                            <div class="form-group">
                                <label for="language">Language</label>
                                <input type="text" id="language" name="language" value="<?= e($formData['language']) ?>" maxlength="10">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Authors Section -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h2 style="margin: 0;">✍️ Authors</h2>
            </div>
            <div class="card-body">
                <div id="authors-section">
                    <?php if (!empty($parsedAuthors)): ?>
                        <div style="margin-bottom: 1rem;">
                            <p style="margin: 0 0 0.5rem 0; color: var(--secondary-color); font-size: 0.875rem;">
                                Parsed from scanned data (uncheck to remove):
                            </p>
                            <?php foreach ($parsedAuthors as $index => $author): ?>
                                <div class="form-check" style="padding: 0.75rem; background: var(--bg-secondary); border-radius: 0.25rem; margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                                    <label style="display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                                        <?php if (!empty($author['existing_id'])): ?>
                                            <input type="checkbox" name="author_ids[]" value="<?= $author['existing_id'] ?>" checked>
                                            <strong><?= e($author['lastname']) ?>, <?= e($author['surname']) ?></strong>
                                            <span style="color: var(--success-color); font-size: 0.875rem;">✓ existing author</span>
                                        <?php else: ?>
                                            <input type="checkbox" name="new_authors[]" value="<?= e($author['surname']) ?>|<?= e($author['lastname']) ?>" checked>
                                            <strong><?= e($author['lastname']) ?>, <?= e($author['surname']) ?></strong>
                                            <span style="color: var(--info-color); font-size: 0.875rem;">⊕ will be created</span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No authors found in scanned data.</p>
                    <?php endif; ?>

                    <details style="margin-top: 1rem;">
                        <summary style="cursor: pointer; color: var(--primary-color); font-weight: 500;">
                            + Add or change authors manually
                        </summary>
                        <div style="margin-top: 1rem; padding: 1rem; background: var(--bg-color); border-radius: 0.25rem;">
                            <label>Select existing authors:</label>
                            <div style="max-height: 200px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 0.25rem; padding: 0.5rem;">
                                <?php foreach ($allAuthors as $author): ?>
                                    <div class="form-check">
                                        <label>
                                            <input type="checkbox" name="author_ids[]" value="<?= $author['id'] ?>">
                                            <?= e($author['lastname']) ?>, <?= e($author['surname']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </details>
                </div>
            </div>
        </div>

        <!-- Category Selection -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h2 style="margin: 0;">🏷️ Category & Tag</h2>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                            <label for="main_category">Main Category *</label>
                            <a href="#" onclick="showNewMainCategoryForm(); return false;" style="font-size: 0.875rem; color: var(--primary-color);">+ New Main Category</a>
                        </div>
                        <select id="main_category" required onchange="updateSubcategories()">
                            <option value="">Select main category...</option>
                            <?php foreach ($categoriesByMain as $mainCat): ?>
                                <option value="<?= e($mainCat['code']) ?>"><?= e($mainCat['title']) ?> (<?= e($mainCat['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Inline form for new main category -->
                        <div id="new-main-category-form" style="display: none; margin-top: 0.75rem; padding: 0.75rem; background: var(--bg-secondary); border-radius: 0.25rem;">
                            <div class="form-group" style="margin-bottom: 0.5rem;">
                                <label for="new_main_code" style="font-size: 0.875rem;">Code (2 letters) *</label>
                                <input type="text" id="new_main_code" maxlength="2" pattern="[A-Z]{2}" style="text-transform: uppercase;" placeholder="e.g., SF">
                            </div>
                            <div class="form-group" style="margin-bottom: 0.5rem;">
                                <label for="new_main_title" style="font-size: 0.875rem;">Title *</label>
                                <input type="text" id="new_main_title" placeholder="e.g., Science Fiction">
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <button type="button" onclick="createMainCategory()" class="btn btn-primary btn-sm">Create</button>
                                <button type="button" onclick="hideNewMainCategoryForm()" class="btn btn-secondary btn-sm">Cancel</button>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                            <label for="code_category">Category *</label>
                            <a href="#" onclick="showNewCategoryForm(); return false;" style="font-size: 0.875rem; color: var(--primary-color);" id="new-category-link">+ New Category</a>
                        </div>
                        <select id="code_category" name="code_category" required disabled onchange="updateTagPreview()">
                            <option value="">Select category...</option>
                        </select>

                        <!-- Inline form for new category -->
                        <div id="new-category-form" style="display: none; margin-top: 0.75rem; padding: 0.75rem; background: var(--bg-secondary); border-radius: 0.25rem;">
                            <div class="form-group" style="margin-bottom: 0.5rem;">
                                <label for="new_cat_code" style="font-size: 0.875rem;">Code (2 letters) *</label>
                                <input type="text" id="new_cat_code" maxlength="2" pattern="[A-Z]{2}" style="text-transform: uppercase;" placeholder="e.g., PH">
                            </div>
                            <div class="form-group" style="margin-bottom: 0.5rem;">
                                <label for="new_cat_title" style="font-size: 0.875rem;">Title *</label>
                                <input type="text" id="new_cat_title" placeholder="e.g., Philosophy">
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <button type="button" onclick="createCategory()" class="btn btn-primary btn-sm">Create</button>
                                <button type="button" onclick="hideNewCategoryForm()" class="btn btn-secondary btn-sm">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="tag-preview" style="display: none; padding: 1rem; background: var(--info-color); color: white; border-radius: 0.25rem; margin-top: 1rem;">
                    <strong>Book tag will be:</strong>
                    <div style="font-family: monospace; font-size: 1.5rem; margin-top: 0.5rem;" id="tag-preview-text"></div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-bottom: 2rem;">
            <a href="/books/import/" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">💾 Save & Import</button>
        </div>
    </form>
</div>

<script>
// Category data from PHP
const categoriesByMain = <?= json_encode(array_values($categoriesByMain)) ?>;

function updateSubcategories() {
    const mainSelect = document.getElementById('main_category');
    const subSelect = document.getElementById('code_category');
    const mainCode = mainSelect.value;

    // Clear subcategories
    subSelect.innerHTML = '<option value="">Select category...</option>';
    subSelect.disabled = !mainCode;

    // Hide tag preview
    document.getElementById('tag-preview').style.display = 'none';

    if (mainCode) {
        const mainCat = categoriesByMain.find(c => c.code === mainCode);
        if (mainCat && mainCat.subcategories) {
            mainCat.subcategories.forEach(cat => {
                const option = document.createElement('option');
                option.value = cat.code;
                option.textContent = `${cat.title} (${cat.code})`;
                subSelect.appendChild(option);
            });
        }
    }
}

async function updateTagPreview() {
    const categoryCode = document.getElementById('code_category').value;
    const previewDiv = document.getElementById('tag-preview');
    const previewText = document.getElementById('tag-preview-text');

    if (!categoryCode) {
        previewDiv.style.display = 'none';
        return;
    }

    try {
        const response = await fetch('/books/import/preview-tag.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ category: categoryCode })
        });

        const data = await response.json();

        if (data.success) {
            previewText.textContent = data.tag;
            previewDiv.style.display = 'block';
        }
    } catch (err) {
        console.error('Failed to get tag preview:', err);
    }
}

// Show/Hide forms for new categories
function showNewMainCategoryForm() {
    document.getElementById('new-main-category-form').style.display = 'block';
    document.getElementById('new_main_code').focus();
}

function hideNewMainCategoryForm() {
    document.getElementById('new-main-category-form').style.display = 'none';
    document.getElementById('new_main_code').value = '';
    document.getElementById('new_main_title').value = '';
}

function showNewCategoryForm() {
    const mainSelect = document.getElementById('main_category');
    if (!mainSelect.value) {
        alert('Please select a main category first.');
        return;
    }
    document.getElementById('new-category-form').style.display = 'block';
    document.getElementById('new_cat_code').focus();
}

function hideNewCategoryForm() {
    document.getElementById('new-category-form').style.display = 'none';
    document.getElementById('new_cat_code').value = '';
    document.getElementById('new_cat_title').value = '';
}

// Create new main category
async function createMainCategory() {
    const code = document.getElementById('new_main_code').value.trim().toUpperCase();
    const title = document.getElementById('new_main_title').value.trim();

    if (!code || !title) {
        alert('Please fill in all fields.');
        return;
    }

    if (code.length !== 2 || !/^[A-Z]{2}$/.test(code)) {
        alert('Code must be exactly 2 uppercase letters.');
        return;
    }

    try {
        const response = await fetch('/categories/add-main.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code, title })
        });

        const data = await response.json();

        if (data.success) {
            // Add to categoriesByMain array
            categoriesByMain.push({
                code: code,
                title: title,
                subcategories: []
            });

            // Add to select dropdown
            const mainSelect = document.getElementById('main_category');
            const option = document.createElement('option');
            option.value = code;
            option.textContent = `${title} (${code})`;
            option.selected = true;
            mainSelect.appendChild(option);

            // Hide form and update subcategories
            hideNewMainCategoryForm();
            updateSubcategories();
        } else {
            alert('Error: ' + (data.message || 'Failed to create main category'));
        }
    } catch (err) {
        alert('Network error: ' + err.message);
    }
}

// Create new category
async function createCategory() {
    const code = document.getElementById('new_cat_code').value.trim().toUpperCase();
    const title = document.getElementById('new_cat_title').value.trim();
    const mainCode = document.getElementById('main_category').value;

    if (!code || !title) {
        alert('Please fill in all fields.');
        return;
    }

    if (code.length !== 2 || !/^[A-Z]{2}$/.test(code)) {
        alert('Code must be exactly 2 uppercase letters.');
        return;
    }

    if (!mainCode) {
        alert('Please select a main category first.');
        return;
    }

    try {
        const response = await fetch('/categories/add-sub.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code, title, main_category: mainCode })
        });

        const data = await response.json();

        if (data.success) {
            // Add to categoriesByMain array
            const mainCat = categoriesByMain.find(c => c.code === mainCode);
            if (mainCat) {
                mainCat.subcategories.push({
                    code: code,
                    title: title,
                    code_maincategory: mainCode
                });
            }

            // Update subcategories dropdown
            updateSubcategories();

            // Select the newly created category
            const subSelect = document.getElementById('code_category');
            subSelect.value = code;

            // Hide form and update tag preview
            hideNewCategoryForm();
            updateTagPreview();
        } else {
            alert('Error: ' + (data.message || 'Failed to create category'));
        }
    } catch (err) {
        alert('Network error: ' + err.message);
    }
}
</script>

<?php include __DIR__ . '/../../../src/Views/layout/footer.php'; ?>
