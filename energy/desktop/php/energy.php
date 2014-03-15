<?php
if (!isConnect('admin')) {
    throw new Exception('Error 401 Unauthorized');
}

?>
