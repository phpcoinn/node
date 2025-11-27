<?php
// PHPCoin Docs Viewer

set_time_limit(5);
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('PHPCOIN_DOCS_VIEWER_VERSION', '0.0.5');

require_once dirname(__DIR__) . '/apps.inc.php';
require_once './Parsedown.php';

register_shutdown_function('shutdown_handler');

function shutdown_handler() {
	print '<p>DEBUG: shutdown_handler @ ' . time() . '</p>';
	$error = error_get_last();
	if (! empty($error)) {
		print '<pre>' . print_r($error, true) . '</pre>';
	}
	print '<hr /><br><br><br>';
}


class ParsedownExt extends Parsedown {
    private $docPath;
    private $baseDir;
    private $realBaseDir;

    public function __construct($docPath, $baseDir)
    {
        $this->docPath = $docPath;
        $this->baseDir = $baseDir;
        $this->realBaseDir = realpath($this->baseDir);
    }

    protected function inlineLink($Excerpt)
    {
        $link = parent::inlineLink($Excerpt);
        $href = $link['element']['attributes']['href'];

        // Don't rewrite external links, mailto links, or anchors
        if (preg_match('/^(https?:\/\/|mailto:|#)/', $href)) {
            return $link;
        }

        // Don't rewrite links to non-markdown files
        $allowedExtensions = ['md', 'png', 'pdf'];
        $extension = pathinfo($href, PATHINFO_EXTENSION);
        if ($extension && !in_array(strtolower($extension), $allowedExtensions)) {
             return $link;
        }

        $currentDocDir = $this->baseDir . ($this->docPath ? $this->docPath . '/' : '');
        $file = $currentDocDir . $href;

        $realFile = realpath($file);

        if ($realFile === false || strpos($realFile, $this->realBaseDir) !== 0) {
            // This is an invalid link, pointing outside the docs directory.
            // Let's make it a dead link and style it to indicate it's broken.
            $link['element']['attributes']['href'] = '#';
            if (isset($link['element']['attributes']['class'])) {
                $link['element']['attributes']['class'] .= ' broken-link';
            } else {
                $link['element']['attributes']['class'] = 'broken-link';
            }
            $link['element']['attributes']['title'] = 'Invalid link (points outside of documentation)';
            return $link;
        }

        if ($realFile == $this->realBaseDir) {
            $newDoc = '';
        } else {
            $newDoc = substr($realFile, strlen($this->realBaseDir) + 1);
            if (is_dir($realFile)) {
                $newDoc .= '/';
            }
        }

        $link['element']['attributes']['href'] = "/apps/docs/index.php?doc=".$newDoc;
        return $link;
    }
}

$docsDir = dirname(dirname(dirname(__DIR__)));
$baseDir = $docsDir.'/docs/';

if(!empty($_GET['doc'])) {
    $link = $_GET['doc'];
    $file = $baseDir . $link;
    if (is_dir($file)) {
        if (substr($link, -1) !== '/') {
            $link .= '/';
        }
        $file = $baseDir . $link . 'README.md';
    }
} else {
    $link = '';
    $file = $baseDir . 'README.md';
}

// Security: Prevent path traversal
$realFile = realpath($file);
$realBaseDir = realpath($baseDir);

if ($realFile === false || strpos($realFile, $realBaseDir) !== 0) {
    define("PAGE", "Docs - Not Found");
    define("APP_NAME", "Docs");
    http_response_code(404);
    require_once __DIR__. '/../common/include/top.php';
    echo "<h1>404 Docs Not Found</h1>";
    require_once __DIR__ . '/../common/include/bottom.php';
    exit;
} else {
    $file = $realFile;
}

$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

if ($extension === 'png') {
    header('Content-Type: image/png');
    readfile($file);
    exit;
} elseif ($extension === 'pdf') {
    header('Content-Type: application/pdf');
    readfile($file);
    exit;
}

$relativePath = str_replace($baseDir, '', $file);
$docPath = dirname($relativePath);
if ($docPath == ".") {
	$docPath = "";
}
$pd = new ParsedownExt($docPath, $baseDir);
$pd->setSafeMode(true);
$text = file_get_contents($file);

define("PAGE", "Docs");
define("APP_NAME", "Docs");

?>
<?php
require_once __DIR__. '/../common/include/top.php';
?>

<?php echo $pd->text($text); ?>

<div class="container-fluid">
    <hr>
    <div class="row">
        <div class="col-sm-6">
            PHPCoin Docs Viewer v<?php echo PHPCOIN_DOCS_VIEWER_VERSION ?>
        </div>
    </div>
</div>


<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>
