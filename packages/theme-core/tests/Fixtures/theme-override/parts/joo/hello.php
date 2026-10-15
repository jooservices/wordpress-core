<?php
echo '<p class="override">Theme says hi to ' . esc_html( (string) ( $data['name'] ?? '' ) ) . '</p>';
