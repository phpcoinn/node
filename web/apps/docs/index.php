<?php
require_once dirname(__DIR__)."/apps.inc.php";
require_once './Parsedown.php';

//error_reporting(E_ALL);
//ini_set('display_errors', 1);

class ParsedownExt extends Parsedown {
    function inlineLink($Excerpt)
    {
        $link = parent::inlineLink($Excerpt);
        $link['element']['attributes']['href'] = "/apps/docs/index.php?link=".urlencode($link['element']['attributes']['href']);
        return $link;
    }
}

$docsDir = dirname(dirname(dirname(__DIR__)));
if(isset($_GET['link'])) {
    $link = $_GET['link'];
    $file = $docsDir.'/docs/' . $link;
} else {
    $file = $docsDir.'/docs/index.md';
}

$pd = new ParsedownExt();
$pd->setSafeMode(true);
$text = file_get_contents($file);

define("PAGE", "Docs");
define("APP_NAME", "Docs");

?>
<?php
require_once __DIR__. '/../common/include/top.php';
?>

<ol class="breadcrumb m-0 ps-0 h4">
    <li class="breadcrumb-item"><a href="/apps/docs">Home</a></li>
</ol>
<?php echo $pd->text($text); ?>

<?php
require_once __DIR__ . '/../common/include/bottom.php';
?>
