<?php
/**
 * Scan Mode - Fast ISBN scanning with barcode scanner
 */

$app = require __DIR__ . '/../../../src/bootstrap.php';
extract($app);

requireAuth();

$pageTitle = 'Scan Mode';

// Get statistics
$stmt = $db->prepare('
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = "imported" THEN 1 ELSE 0 END) as imported
    FROM scanned_books
');
$stmt->execute();
$stats = $stmt->fetch();

include __DIR__ . '/../../../src/Views/layout/header.php';
?>

    <div class="container" style="max-width: 900px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h1>📚 Scan Mode</h1>
                <p class="subtitle">Scan ISBNs with your barcode scanner</p>
            </div>
            <a href="/books/" class="btn btn-secondary">Exit Scan Mode</a>
        </div>

        <div
            x-data="{
                isbn: '',
                scanning: false,
                lastScanned: null,
                history: [],
                stats: {
                    scanned: <?= (int)$stats['total'] ?>,
                    pending: <?= (int)$stats['pending'] ?>,
                    imported: <?= (int)$stats['imported'] ?>,
                    sessionScans: 0,
                    duplicates: 0,
                    errors: 0
                },

                async scanISBN() {
                    if (!this.isbn.trim() || this.scanning) return;

                    this.scanning = true;
                    const isbnValue = this.isbn.trim();
                    this.isbn = '';

                    try {
                        const response = await fetch('/books/scan-isbn.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({ isbn: isbnValue })
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.lastScanned = data.book;
                            this.history.unshift(data.book);
                            if (this.history.length > 10) {
                                this.history.pop();
                            }
                            this.stats.scanned++;
                            this.stats.pending++;
                            this.stats.sessionScans++;
                        } else {
                            if (data.error === 'already_scanned' || data.error === 'already_in_collection') {
                                this.stats.duplicates++;
                                this.showError(data.message);
                            } else {
                                this.stats.errors++;
                                this.showError(data.message || 'Failed to scan ISBN');
                            }
                        }
                    } catch (error) {
                        this.stats.errors++;
                        this.showError('Network error. Please try again.');
                    } finally {
                        this.scanning = false;
                        this.$nextTick(() => {
                            this.$refs.isbnInput.focus();
                        });
                    }
                },

                async deleteLastScanned() {
                    if (!this.lastScanned || this.scanning) return;

                    if (!confirm('Delete this scanned book?')) return;

                    this.scanning = true;

                    try {
                        const response = await fetch('/books/scan/delete.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({ id: this.lastScanned.id })
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.stats.scanned--;
                            this.stats.pending--;
                            this.stats.sessionScans--;

                            // Remove from history
                            const deletedId = this.lastScanned.id;
                            this.history = this.history.filter(b => b.id !== deletedId);

                            // Set new lastScanned to the most recent book in history
                            this.lastScanned = this.history.length > 0 ? this.history[0] : null;
                        } else {
                            this.showError('Failed to delete book');
                        }
                    } catch (error) {
                        this.showError('Network error. Please try again.');
                    } finally {
                        this.scanning = false;
                        this.$nextTick(() => {
                            this.$refs.isbnInput.focus();
                        });
                    }
                },

                showError(message) {
                    alert(message);
                },

                init() {
                    this.$nextTick(() => {
                        this.$refs.isbnInput.focus();
                    });
                }
            }"
            x-init="init()"
        >
            <!-- Scan Input -->
            <div class="card" style="margin-bottom: 2rem;">
                <form @submit.prevent="scanISBN">
                    <div class="form-group">
                        <label for="isbn">Scan ISBN</label>
                        <input
                            type="text"
                            id="isbn"
                            x-ref="isbnInput"
                            x-model="isbn"
                            placeholder="Scan barcode or enter ISBN..."
                            :disabled="scanning"
                            autocomplete="off"
                            autofocus
                            style="font-size: 1.2rem; padding: 1rem;"
                        >
                        <small style="color: var(--secondary-color);">
                            Position your scanner and scan the ISBN barcode
                        </small>
                    </div>
                </form>

                <!-- Stats -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                    <div style="text-align: center;">
                        <div style="font-size: 2rem; font-weight: bold; color: var(--primary-color);" x-text="stats.sessionScans"></div>
                        <div style="font-size: 0.875rem; color: var(--secondary-color);">This Session</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 2rem; font-weight: bold; color: var(--success-color);" x-text="stats.scanned"></div>
                        <div style="font-size: 0.875rem; color: var(--secondary-color);">Total Scanned</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 2rem; font-weight: bold; color: var(--warning-color);" x-text="stats.duplicates"></div>
                        <div style="font-size: 0.875rem; color: var(--secondary-color);">Duplicates</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 2rem; font-weight: bold; color: var(--danger-color);" x-text="stats.errors"></div>
                        <div style="font-size: 0.875rem; color: var(--secondary-color);">Errors</div>
                    </div>
                </div>

                <!-- Loading indicator -->
                <div x-show="scanning" style="margin-top: 1rem; text-align: center; color: var(--primary-color);">
                    <div style="display: inline-block; animation: spin 1s linear infinite;">⟳</div>
                    <span style="margin-left: 0.5rem;">Processing...</span>
                </div>
            </div>

            <!-- Last Scanned Book -->
            <div x-show="lastScanned" class="card" style="margin-bottom: 2rem;">
                <h2 style="margin-top: 0; margin-bottom: 1rem;">Last Scanned</h2>

                <div style="display: flex; gap: 1.5rem; align-items: start;">
                    <div style="flex-shrink: 0; width: 120px; height: 180px; overflow: hidden; border-radius: 0.375rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); background: #e9ecef;">
                        <img
                            :src="lastScanned?.cover_url || '/images/no-cover.svg'"
                            alt=""
                            style="width: 100%; height: 100%; object-fit: cover;"
                            onerror="this.src='/images/no-cover.svg'"
                        >
                    </div>

                    <div style="flex: 1; min-width: 0;">
                        <h3 style="margin: 0 0 0.5rem 0;" x-text="lastScanned?.title"></h3>
                        <p style="color: var(--secondary-color); margin: 0 0 0.5rem 0;" x-show="lastScanned?.subtitle" x-text="lastScanned?.subtitle"></p>
                        <p style="margin: 0 0 0.5rem 0;">
                            <strong>by</strong> <span x-text="lastScanned?.authors || 'Unknown Author'"></span>
                        </p>
                        <p style="color: var(--secondary-color); margin: 0; font-size: 0.875rem;">
                            <span x-show="lastScanned?.published_year" x-text="lastScanned?.published_year"></span>
                            <span x-show="lastScanned?.published_year && lastScanned?.pages"> • </span>
                            <span x-show="lastScanned?.pages"><span x-text="lastScanned?.pages"></span> pages</span>
                            <span x-show="lastScanned?.publisher"> • </span>
                            <span x-show="lastScanned?.publisher" x-text="lastScanned?.publisher"></span>
                        </p>

                        <div style="margin-top: 1rem; display: flex; gap: 1rem; align-items: center;">
                            <span style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--success-color); font-weight: 500;">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Saved
                            </span>
                            <button
                                type="button"
                                @click="deleteLastScanned"
                                class="btn btn-danger btn-sm"
                                :disabled="scanning"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scan History -->
            <div x-show="history.length > 0" class="card">
                <h2 style="margin-top: 0; margin-bottom: 1rem;">Recent Scans</h2>

                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <template x-for="book in history.slice(1)" :key="book.id">
                        <div style="display: flex; gap: 1rem; padding: 0.75rem; background: var(--bg-secondary); border-radius: 0.375rem; align-items: center;">
                            <div style="flex-shrink: 0; width: 60px; height: 90px; overflow: hidden; border-radius: 0.25rem; background: #e9ecef;">
                                <img
                                    :src="book.cover_url || '/images/no-cover.svg'"
                                    alt=""
                                    style="width: 100%; height: 100%; object-fit: cover;"
                                    onerror="this.src='/images/no-cover.svg'"
                                >
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-weight: 500; margin-bottom: 0.25rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" x-text="book.title"></div>
                                <div style="font-size: 0.875rem; color: var(--secondary-color); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" x-text="book.authors || 'Unknown Author'"></div>
                                <div style="font-size: 0.75rem; color: var(--success-color); margin-top: 0.25rem;">✓ Saved</div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div style="margin-top: 2rem; text-align: center; color: var(--secondary-color); font-size: 0.875rem;">
            <p>Need to review your scanned books? Go to <a href="/books/import/">Import Manager</a></p>
        </div>
    </div>

    <style>
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>

<?php include __DIR__ . '/../../../src/Views/layout/footer.php'; ?>
