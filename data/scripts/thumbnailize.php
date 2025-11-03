#!/usr/bin/env php
<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (!function_exists('pcntl_fork')) {
    die("pcntl extension is required for parallel processing.\n");
}

// ------------------- DEFAULT CONFIG -------------------
$MAIN_DIR = "files";
$ORIGINAL_DIR = "$MAIN_DIR/original";
$LARGE_DIR = "$MAIN_DIR/large";
$MEDIUM_DIR = "$MAIN_DIR/medium";
$SQUARE_DIR = "$MAIN_DIR/square";

$LARGE_SIZE = 800;
$MEDIUM_SIZE = 200;
$SQUARE_SIZE = 200;

$LOG_FILE = "thumbnail.log";
$MODE = "missing";
$DRYRUN = false;
$PARALLEL = 1;
$PROGRESS = true;
$PDF_DPI = 150;
$CROP_MODE = "centre";

// ------------------- PARSE OPTIONS -------------------
$options = getopt("", [
    "all", "missing", "parallel:", "dry-run", "log-file:", "no-progress",
    "pdf-dpi:", "crop-mode:", "main-dir:", "help"
]);

if (isset($options['help'])) {
    echo "Usage: php thumbnailize.php [OPTIONS]\n";
    echo "--all                Process all files\n";
    echo "--missing            Process only missing (default)\n";
    echo "--parallel N         Number of parallel processes\n";
    echo "--dry-run            Show actions only\n";
    echo "--log-file FILE      Log file\n";
    echo "--no-progress        Disable progress bar\n";
    echo "--pdf-dpi N          DPI for PDFs\n";
    echo "--crop-mode MODE     centre|entropy|attention|face|document\n";
    echo "--main-dir DIR       Main directory (default: files)\n";
    exit(0);
}

if (isset($options['all'])) $MODE = "all";
if (isset($options['missing'])) $MODE = "missing";
if (isset($options['parallel'])) $PARALLEL = max(1, (int)$options['parallel']);
if (isset($options['dry-run'])) $DRYRUN = true;
if (isset($options['log-file'])) $LOG_FILE = $options['log-file'];
if (isset($options['no-progress'])) $PROGRESS = false;
if (isset($options['pdf-dpi'])) $PDF_DPI = (int)$options['pdf-dpi'];
if (isset($options['crop-mode'])) $CROP_MODE = $options['crop-mode'];
if (isset($options['main-dir'])) {
    $MAIN_DIR = rtrim($options['main-dir'], "/");
    $ORIGINAL_DIR = "$MAIN_DIR/original";
    $LARGE_DIR = "$MAIN_DIR/large";
    $MEDIUM_DIR = "$MAIN_DIR/medium";
    $SQUARE_DIR = "$MAIN_DIR/square";
}

// ------------------- CHECK VIPS -------------------
exec("which vips", $out, $ret);
if ($ret !== 0) {
    die("Error: VIPS is required but not found.\n");
}

// ------------------- PREP -------------------
@mkdir($LARGE_DIR, 0777, true);
@mkdir($MEDIUM_DIR, 0777, true);
@mkdir($SQUARE_DIR, 0777, true);
touch($LOG_FILE);

$files = glob("$ORIGINAL_DIR/*.{jpg,jpeg,png,webp,tif,tiff,pdf,JPG,JPEG,PNG,WEBP,TIF,TIFF,PDF}", GLOB_BRACE);
$total = count($files);
$count = 0;

// ------------------- HELPERS -------------------
function detectType(string $file): string {
    exec("vipsheader -f format " . escapeshellarg($file), $out, $ret);
    if ($ret === 0 && !empty($out)) return trim($out[0]);
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    switch ($ext) {
        case "pdf": return "pdfload";
        case "jpg": case "jpeg": return "jpeg";
        case "png": return "png";
        case "tif": case "tiff": return "tiff";
        case "webp": return "webp";
        default: return "unknown";
    }
}

function logMsg(string $msg, string $logFile) {
    file_put_contents($logFile, $msg . "\n", FILE_APPEND);
}

function progressBar(int $count, int $total) {
    $width = 40;
    $percent = intval($count * 100 / max($total,1));
    $filled = intval($width * $count / max($total,1));
    $empty = $width - $filled;
    printf("\r[" . str_repeat("#", $filled) . str_repeat("-", $empty) . "] %d%% (%d/%d)", $percent, $count, $total);
}

function processFile(string $img, array $config) {
    $base = basename($img);
    $filetype = detectType($img);
    if ($filetype === "unknown") {
        logMsg("[skip] Unknown: $base", $config['log_file']);
        return;
    }

    $filename = pathinfo($base, PATHINFO_FILENAME) . ".jpg";
    $large_out = $config['large_dir'] . "/$filename";
    $medium_out = $config['medium_dir'] . "/$filename";
    $square_out = $config['square_dir'] . "/$filename";

    if ($config['mode'] === "missing" && file_exists($large_out) && file_exists($medium_out) && file_exists($square_out)) {
        logMsg("[skip] $base", $config['log_file']);
        return;
    }

    logMsg("[process] $base ($filetype)", $config['log_file']);

    $thumbnail_input = $img;
    $is_pdf = false;
    $temp_flattened = "";

    if ($filetype === "pdfload") {
        $is_pdf = true;
        $temp_flattened = tempnam(sys_get_temp_dir(), "pdf_flat_") . ".jpg";
        if (!$config['dryrun']) {
            $cmd = sprintf(
                "vips pdfload %s %s --page=0 --dpi=%d --n=1 --access=sequential --flatten --background '255 255 255'",
                escapeshellarg($img),
                escapeshellarg($temp_flattened),
                $config['pdf_dpi']
            );
            exec($cmd);
        }
        $thumbnail_input = $temp_flattened;
    }

    if (!$config['dryrun']) {
        exec("vips thumbnail " . escapeshellarg($thumbnail_input) . " " . escapeshellarg($large_out) . " {$config['large_size']} --size=down");
        exec("vips thumbnail " . escapeshellarg($thumbnail_input) . " " . escapeshellarg($medium_out) . " {$config['medium_size']} --size=down");

        switch ($config['crop_mode']) {
            case "centre": case "center":
                exec("vips thumbnail " . escapeshellarg($thumbnail_input) . " " . escapeshellarg($square_out) . " {$config['square_size']}x{$config['square_size']} --crop=centre");
                break;
            case "entropy":
            case "attention":
            case "face":
            case "document":
                exec("vips smartcrop " . escapeshellarg($thumbnail_input) . " " . escapeshellarg($square_out) . " {$config['square_size']} {$config['square_size']} --interesting={$config['crop_mode']}");
                break;
            default:
                exec("vips thumbnail " . escapeshellarg($thumbnail_input) . " " . escapeshellarg($square_out) . " {$config['square_size']}x{$config['square_size']} --crop=centre");
        }
    }

    if ($is_pdf && !$config['dryrun']) {
        @unlink($temp_flattened);
    }
}

// ------------------- CONFIG ARRAY -------------------
$config = [
    'mode' => $MODE,
    'dryrun' => $DRYRUN,
    'log_file' => $LOG_FILE,
    'large_dir' => $LARGE_DIR,
    'medium_dir' => $MEDIUM_DIR,
    'square_dir' => $SQUARE_DIR,
    'large_size' => $LARGE_SIZE,
    'medium_size' => $MEDIUM_SIZE,
    'square_size' => $SQUARE_SIZE,
    'pdf_dpi' => $PDF_DPI,
    'crop_mode' => $CROP_MODE
];

echo "Mode: $MODE\nParallel jobs: $PARALLEL\nDry-run: " . ($DRYRUN ? "true" : "false") . "\nProgress: " . ($PROGRESS ? "true" : "false") . "\nPDF DPI: $PDF_DPI\nCrop mode: $CROP_MODE\nLog-file: $LOG_FILE\nFound $total files\n\n";
echo "Starting...\n";

// ------------------- PARALLEL PROCESSING -------------------
if ($PARALLEL > 1) {
    $pool = [];
    $finishedCount = 0;
    foreach ($files as $img) {
        // Limit number of concurrent children
        while (count($pool) >= $PARALLEL) {
            foreach ($pool as $key => $pid) {
                $res = pcntl_waitpid($pid, $status, WNOHANG);
                if ($res > 0) {
                    unset($pool[$key]);
                    $finishedCount++;
                    if ($PROGRESS) progressBar($finishedCount, $total);
                }
            }
            usleep(50000);
        }

        $pid = pcntl_fork();
        if ($pid == -1) {
            die("Failed to fork process\n");
        } elseif ($pid) {
            // parent
            $pool[] = $pid;
        } else {
            // child
            processFile($img, $config);
            exit(0);
        }
    }

    // Wait for remaining children
    while (count($pool) > 0) {
        foreach ($pool as $key => $pid) {
            $res = pcntl_waitpid($pid, $status, WNOHANG);
            if ($res > 0) {
                unset($pool[$key]);
                $finishedCount++;
                if ($PROGRESS) progressBar($finishedCount, $total);
            }
        }
        usleep(50000);
    }

} else {
    foreach ($files as $img) {
        processFile($img, $config);
        if ($PROGRESS) {
            $count++;
            progressBar($count, $total);
        }
    }
}

echo "\nDONE.\n";
