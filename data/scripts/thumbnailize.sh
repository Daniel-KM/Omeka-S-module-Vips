#!/usr/bin/env bash
[ -z "$BASH_VERSION" ] && exec bash "$0" "$@"

set -euo pipefail

############################################
# DEFAULT CONFIGURATION
############################################
MAIN_DIR="files"
ORIGINAL_DIR="$MAIN_DIR/original"
LARGE_DIR="$MAIN_DIR/large"
MEDIUM_DIR="$MAIN_DIR/medium"
SQUARE_DIR="$MAIN_DIR/square"

LARGE_SIZE=800
MEDIUM_SIZE=200
SQUARE_SIZE=200

LOG_FILE="thumbnail.log"
MODE="missing"
DRYRUN=false
PARALLEL=1
PROGRESS=true
PDF_DPI=150
CROP_MODE="centre"

TMPCOUNT=$(mktemp /tmp/thumb_count_XXXXXX.tmp)
touch "$TMPCOUNT"

############################################
# CHECK DEPENDENCIES
############################################
if ! command -v vips &>/dev/null; then
    echo "Error: VIPS is required but not found."
    echo "Please install libvips (e.g., 'sudo apt install libvips-tools' or 'brew install vips')."
    exit 1
fi

# Only check GNU parallel if using parallel > 1
if [[ "$PARALLEL" -gt 1 ]] && ! command -v parallel &>/dev/null; then
    echo "Error: GNU parallel is required for parallel processing."
    echo "Please install it (e.g., 'sudo apt install parallel' or 'brew install parallel')."
    exit 1
fi

############################################
# HELP
############################################
usage() {
    cat <<EOF
Usage: $0 [OPTIONS]

Options:
  --all                Process all files (overwrite existing)
  --missing            Process only missing thumbnails (default)
  --parallel N         Run N parallel jobs
  --dry-run            Show actions but do not run vips
  --log-file FILE      Set log file (default: thumbnail.log)
  --no-progress        Disable progress bar
  --pdf-dpi N          Set DPI for PDF rendering (default: 150)
  --crop-mode MODE     Smart crop mode: centre (default), face, entropy, attention, document
  --main-dir DIR       Main directory for files/ subfolders (default: files/)
  --help               Show this help message

Examples:

# Process all files in the original directory, creating thumbnails:
$0 --all

# Process only missing thumbnails (default behavior):
$0

# Use 4 parallel jobs for faster processing:
$0 --parallel 4

# Dry-run to see what would be done without writing files:
$0 --dry-run

# Specify pdf dpi and log file:
$0 --pdf-dpi 200 --log-file mylog.log

# Use smart face-aware cropping for square thumbnails:
$0 --crop-mode face

# Combine options: parallel + all + dry-run + entropy crop:
$0 --all --parallel 8 --dry-run --crop-mode entropy
EOF
}

############################################
# PARSE ARGUMENTS
############################################
while [[ $# -gt 0 ]]; do
    case "$1" in
        --all) MODE="all" ;;
        --missing) MODE="missing" ;;
        --parallel) PARALLEL="$2"; shift ;;
        --dry-run) DRYRUN=true ;;
        --log-file) LOG_FILE="$2"; shift ;;
        --no-progress) PROGRESS=false ;;
        --pdf-dpi) PDF_DPI="$2"; shift ;;
        --crop-mode) CROP_MODE="$2"; shift ;;
        --main-dir) MAIN_DIR="$2"; shift ;;
        --help) usage; exit 0 ;;
        *) echo "Unknown option: $1"; usage; exit 1 ;;
    esac
    shift
done

# Update directories based on MAIN_DIR
ORIGINAL_DIR="$MAIN_DIR/original"
LARGE_DIR="$MAIN_DIR/large"
MEDIUM_DIR="$MAIN_DIR/medium"
SQUARE_DIR="$MAIN_DIR/square"

############################################
# PREP
############################################
mkdir -p "$LARGE_DIR" "$MEDIUM_DIR" "$SQUARE_DIR"
touch "$LOG_FILE"
shopt -s nullglob

file_list=$(mktemp /tmp/thumb_files_XXXXXX.txt)
find "$ORIGINAL_DIR" -maxdepth 1 -type f \
     \( -iname "*.jpg" -o -iname "*.jpeg" -o -iname "*.png" -o -iname "*.webp" -o -iname "*.tif" -o -iname "*.tiff" -o -iname "*.pdf" \) \
     > "$file_list"

TOTAL=$(wc -l < "$file_list")
COUNT=0

echo "Mode: $MODE"
echo "Parallel jobs: $PARALLEL"
echo "Dry-run: $DRYRUN"
echo "Progress bar: $PROGRESS"
echo "PDF DPI: $PDF_DPI"
echo "Crop mode: $CROP_MODE"
echo "Log-file: $LOG_FILE"
echo "Main directory: $MAIN_DIR"
echo "Found $TOTAL files"
echo

############################################
# TYPE DETECTION
############################################
detect_type() {
    local file="$1"
    if fmt=$(vipsheader -f format "$file" 2>/dev/null); then
        echo "$fmt"
        return
    fi
    case "$file" in
        *.pdf|*.PDF) echo "pdfload" ;;
        *.jpg|*.jpeg|*.JPG|*.JPEG) echo "jpeg" ;;
        *.png|*.PNG) echo "png" ;;
        *.tif|*.tiff|*.TIF|*.TIFF) echo "tiff" ;;
        *.webp|*.WEBP) echo "webp" ;;
        *) echo "unknown" ;;
    esac
}

############################################
# PROGRESS BAR
############################################
progress_bar() {
    [[ "$PROGRESS" == false ]] && return
    local width=40
    local percent=$((100 * COUNT / (TOTAL == 0 ? 1 : TOTAL)))
    local filled=$((width * COUNT / (TOTAL == 0 ? 1 : TOTAL)))
    local empty=$((width - filled))
    printf "\r["
    printf "%0.s#" $(seq 1 $filled)
    printf "%0.s-" $(seq 1 $empty)
    printf "] %d%% (%d/%d)" "$percent" "$COUNT" "$TOTAL"
}

############################################
# PROCESS SINGLE FILE
############################################
process_file() {
    local img="$1"
    local base
    base=$(basename "$img")
    local filetype
    filetype=$(detect_type "$img")

    if [[ "$filetype" == "unknown" ]]; then
        echo "[skip] Unknown: $base" >> "$LOG_FILE"
        return
    fi

    # Replace extension with .jpg
    local filename="${base%.*}.jpg"
    local large_out="$LARGE_DIR/$filename"
    local medium_out="$MEDIUM_DIR/$filename"
    local square_out="$SQUARE_DIR/$filename"

    if [[ "$MODE" == "missing" ]]; then
        if [[ -f "$large_out" && -f "$medium_out" && -f "$square_out" ]]; then
            echo "[skip]   $base" >> "$LOG_FILE"
            return
        fi
    fi

    echo "[process] $base ($filetype)" >> "$LOG_FILE"

    local thumbnail_input="$img"
    local temp_flattened=""
    local is_pdf=false

    # PDF handling
    if [[ "$filetype" == "pdfload" ]]; then
        is_pdf=true
        temp_flattened=$(mktemp /tmp/pdf_flat_XXXXXX.jpg)
        if [[ "$DRYRUN" == false ]]; then
            vips pdfload "$img" "$temp_flattened" \
                --page=0 \
                --dpi=$PDF_DPI \
                --n=1 \
                --access=sequential \
                --flatten \
                --background "255 255 255"
        fi
        thumbnail_input="$temp_flattened"
    fi

    # Create large and medium
    if [[ "$DRYRUN" == false ]]; then
        vips thumbnail "$thumbnail_input" "$large_out"  $LARGE_SIZE --size=down
        vips thumbnail "$thumbnail_input" "$medium_out" $MEDIUM_SIZE --size=down
    fi

    # Smart crop for square
    if [[ "$DRYRUN" == false ]]; then
        case "$CROP_MODE" in
            centre|center)
                vips thumbnail "$thumbnail_input" "$square_out" \
                    ${SQUARE_SIZE}x${SQUARE_SIZE} --crop=centre
                ;;
            face)
                vips smartcrop "$thumbnail_input" "$square_out" \
                    ${SQUARE_SIZE} ${SQUARE_SIZE} --interesting=attention
                ;;
            entropy)
                vips smartcrop "$thumbnail_input" "$square_out" \
                    ${SQUARE_SIZE} ${SQUARE_SIZE} --interesting=entropy
                ;;
            attention)
                vips smartcrop "$thumbnail_input" "$square_out" \
                    ${SQUARE_SIZE} ${SQUARE_SIZE} --interesting=attention
                ;;
            document)
                vips smartcrop "$thumbnail_input" "$square_out" \
                    ${SQUARE_SIZE} ${SQUARE_SIZE} --interesting=entropy
                ;;
            *)
                echo "[warn] Unknown crop mode '$CROP_MODE', fallback centre" >> "$LOG_FILE"
                vips thumbnail "$thumbnail_input" "$square_out" \
                    ${SQUARE_SIZE}x${SQUARE_SIZE} --crop=centre
                ;;
        esac
    fi

    [[ "$is_pdf" == true && "$DRYRUN" == false ]] && rm -f "$temp_flattened"

    echo 1 >> "$TMPCOUNT"
}

############################################
# EXPORT FUNCTIONS AND VARIABLES FOR PARALLEL
############################################
export -f process_file detect_type progress_bar
export MODE DRYRUN LOG_FILE LARGE_DIR MEDIUM_DIR SQUARE_DIR \
       LARGE_SIZE MEDIUM_SIZE SQUARE_SIZE PDF_DPI CROP_MODE TMPCOUNT

############################################
# RUN SERIAL
############################################
run_serial() {
    while read -r img; do
        process_file "$img"
        COUNT=$(wc -l < "$TMPCOUNT")
        progress_bar
    done < "$file_list"
}

############################################
# RUN PARALLEL
############################################
run_parallel() {
    # Start progress bar updater in background
    (
      while true; do
        COUNT=$(wc -l < "$TMPCOUNT")
        progress_bar
        sleep 0.2
        [[ $COUNT -ge $TOTAL ]] && break
      done
    ) &
    PROGRESS_PID=$!

    cat "$file_list" | parallel -j "$PARALLEL" process_file {}

    wait
    kill $PROGRESS_PID 2>/dev/null || true
}

############################################
# EXECUTION
############################################
echo "Starting…"
echo "Logging to $LOG_FILE"
echo

if [[ "$PARALLEL" -gt 1 ]]; then
    run_parallel
else
    run_serial
fi

echo
echo "DONE."

# Cleanup
rm -f "$TMPCOUNT" "$file_list"
