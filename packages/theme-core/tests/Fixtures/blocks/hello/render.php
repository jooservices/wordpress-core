<?php
// phpcs:ignoreFile
use JOOservices\ThemeCore\Blocks\Html;
echo '<div ' . $wrapper . '>Hello ' . Html::e( (string) ( $data['name'] ?? '' ) ) . '</div>';
