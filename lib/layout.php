<?php

function head($title= 'Raw Materials Art Supplies') {
  header("content-type: text/html;charset=utf-8");?>
<!DOCTYPE html>
<html>
<head>
 <title><?=ashtml($title)?></title>
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <link rel="stylesheet" type="text/css"
       href="<?=BASE?>externals/bootstrap/css/bootstrap.min.css">
 <link rel="stylesheet" type="text/css"
       href="<?=BASE?>style.css">
 <!-- Directa Serif Heavy -->
 <link rel="stylesheet" type="text/css"
       href="http://i.rawm.us/font/MyFontsWebfontsKit.css">
</head>
<body>
<div id="wrap">
  <header class="navbar navbar-default navbar-fixed-top" role="navigation">
    <div class="container">
      <div class="navbar-header">
        <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
          <span class="sr-only">Toggle navigation</span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
        </button>
        <span class="navbar-brand"><a href="http://rawmaterialsla.com/">Raw Materials Art Supplies</a></span>
      </div>
      <div class="collapse navbar-collapse">
        <ul id="navbar" class="nav navbar-nav">
          <li><a href="<?=BASE?>">Home</a></li>
          <li><a href="<?=BASE?>about">About</a></li>
          <li><a href="<?=BASE?>art-supplies">Art Supplies</a></li>
          <li><a href="<?=BASE?>framing">Custom Framing</a></li>
          <li><a href="<?=BASE?>printing">Digital Printing</a></li>
        </ul>
      </div><!--/.nav-collapse -->
    </div>
  </header>
  <div id="page-content" class="container">
<?
}

function foot() {
  global $start_time;
  $finish_time= microtime();

  list($secs, $usecs)= explode(' ', $start_time);
  $start= $secs + $usecs;

  list($secs, $usecs)= explode(' ', $finish_time);
  $finish= $secs + $usecs;

  $time= sprintf("%0.3f", $finish - $start);
?>
  </div><!-- .container -->
</div><!-- #wrap -->
<footer class="container">
 <div class="panel panel-default">
   <div class="panel-footer small">
     <div class="clearfix">
       <div class="pull-right col-sm-3" id="time">
         Page generated in <?=$time?> seconds.
       </div>
       Copyright &copy; 2013 <a href="http://rawmaterialsla.com/">Raw Materials Art Supplies</a>
      </div>
   </div>
 </div>
</footer>
<?if ($GLOBALS['DEBUG']) {?>
  <div id="corner-banner">DEBUG</div>
<?}?>
<script src="<?=BASE?>externals/jquery/jquery-1.10.2.min.js"></script>
<script src="<?=BASE?>externals/bootstrap/js/bootstrap.min.js"></script>
<script src="<?=BASE?>externals/knockout/knockout-3.0.0.js"></script>
<script src="<?=BASE?>externals/knockout/knockout.mapping-2.4.1.js"></script>
<script>
$(function() {

  // dynamically set active navbar link based on script
  var page= '<?=basename($_SERVER['SCRIPT_NAME'], '.php')?>';
  if (page == 'index.php') page= './';
  $("#navbar a[href='<?=BASE?>" + page + "']").parent().addClass('active');

});
</script>
<?
}
