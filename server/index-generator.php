<?php

require __DIR__.'/vendor/autoload.php';


if (php_sapi_name() !== 'cli') {
  http_response_code(404);
  die();
}

?><!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Handsfree Item Index Manipulator</title>
    <style>
      body {
        font-family: sans-serif;
        margin: 0;
        padding: 0;
      }
      .content {
        margin: 0.5rem auto;
        padding: 0 0.5rem;
        max-width: 900px;
      }
      .content h1 {
        text-align: center;
      }
      .content code {
        word-break: break-all;
      }
      .content img {
        max-width: 100%;
      }
      .content table {
        border-spacing: 0;
        border-collapse: collapse;
      }
      .content th, .content td {
        border: 1px solid #DDD;
        padding: 0.5rem;
      }
      .content header {
        margin: 2rem 0;
        text-align: center;
      }
      .content header a {
        color: inherit;
      }
      .content footer {
        margin: 2rem 0;
        text-align: center;
        font-size: 0.8rem;
        color: #999;
      }
      .content footer a {
        color: inherit;
      }
    </style>
  </head>
  <body>
    <div class="content">
      <header><a href="https://github.com/FiveYellowMice/handsfree-item-index-manipulator">GitHub</a></header>

      <?php
      echo Michelf\MarkdownExtra::defaultTransform(file_get_contents(__DIR__.'/../README.md'));
      ?>

      <h2 id="privacy">Privacy</h2>
      <p>There is literally no data stored on this server. All your data still are Google's, either stored in Google Assistant's memory (authentication token and your linked spreadsheets), or in your spreadsheets.</p>

      <footer>
        Made with 🥕 by <a href="https://fym.moe/">FiveYellowMice</a>
      </footer>
    </div>
  </body>
</html>
