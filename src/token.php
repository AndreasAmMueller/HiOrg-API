<?php

/**
 * token.php
 *
 * (c) Andreas Mueller <webmaster@am-wd.de>
 *
 * @description
 * This snippet just prints the received token.
 */

echo isset($_GET['token']) ? $_GET['token'] : 'NO TOKEN GIVEN';

?>