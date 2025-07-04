<?php
session_start();
session_destroy();
header('Location: login.php'); // Перенаправляем на страницу входа
exit();

?>