#!/bin/bash
#
# OpenBookManager - ZPL Label Print Script
#
# This script sends ZPL files to a Zebra thermal printer.
# It's designed to be used as the default application for .zpl files
# via "Open with..." in your file manager.
#
# SETUP INSTRUCTIONS:
#
# 1. Copy this script to your local bin directory:
#    mkdir -p ~/bin
#    cp scripts/print-zpl.sh ~/bin/
#    chmod +x ~/bin/print-zpl.sh
#
# 2. Configure your Zebra printer as a raw queue in CUPS:
#    # Find your printer's USB path:
#    lpinfo -v | grep -i zebra
#
#    # Create raw print queue (adjust the URI to match your printer):
#    sudo lpadmin -p zebra_raw -E -v usb://Zebra/ZD220 -m raw
#
#    # Or for network printers:
#    sudo lpadmin -p zebra_raw -E -v socket://192.168.1.100:9100 -m raw
#
# 3. Set PRINTER_NAME below to match your configured queue name
#
# 4. Associate .zpl files with this script:
#    - Right-click any .zpl file in your file manager
#    - Choose "Open with..." or "Open with other application..."
#    - Select this script (~/bin/print-zpl.sh)
#    - Check "Remember this application" or "Set as default"
#
# TROUBLESHOOTING:
#
# - Test your printer: echo "~JA" | lp -d zebra_raw -o raw
#   (This prints the printer configuration label)
#
# - Check printer status: lpstat -p zebra_raw
#
# - View CUPS error log: tail -f /var/log/cups/error_log
#
# - Test ZPL online: http://labelary.com/viewer.html
#

# ==============================================================================
# CONFIGURATION - Adjust these settings for your environment
# ==============================================================================

# Name of your CUPS print queue (must match the name used in lpadmin)
PRINTER_NAME="zebra_raw"

# Show desktop notification after printing (requires notify-send)
SHOW_NOTIFICATION=true

# ==============================================================================
# SCRIPT LOGIC - No changes needed below this line
# ==============================================================================

# Check if a file was provided
if [ -z "$1" ]; then
    echo "Usage: $0 <file.zpl>"
    echo "Error: No ZPL file specified."
    exit 1
fi

ZPL_FILE="$1"

# Check if file exists
if [ ! -f "$ZPL_FILE" ]; then
    echo "Error: File not found: $ZPL_FILE"
    exit 1
fi

# Check if file has .zpl extension (warning only)
if [[ ! "$ZPL_FILE" =~ \.zpl$ ]]; then
    echo "Warning: File does not have .zpl extension"
fi

# Check if printer exists
if ! lpstat -p "$PRINTER_NAME" &>/dev/null; then
    echo "Error: Printer '$PRINTER_NAME' not found."
    echo ""
    echo "Available printers:"
    lpstat -p -d 2>/dev/null || echo "  (no printers found)"
    echo ""
    echo "To set up your Zebra printer, run:"
    echo "  sudo lpadmin -p $PRINTER_NAME -E -v usb://Zebra/ZD220 -m raw"
    exit 1
fi

# Send ZPL to printer
echo "Sending $ZPL_FILE to printer $PRINTER_NAME..."

if lp -d "$PRINTER_NAME" -o raw "$ZPL_FILE"; then
    echo "Success: Label sent to printer."

    # Show desktop notification if enabled and notify-send is available
    if [ "$SHOW_NOTIFICATION" = true ] && command -v notify-send &>/dev/null; then
        FILENAME=$(basename "$ZPL_FILE")
        notify-send "Label Printed" "Sent $FILENAME to $PRINTER_NAME" --icon=printer
    fi

    exit 0
else
    echo "Error: Failed to send label to printer."
    exit 1
fi
