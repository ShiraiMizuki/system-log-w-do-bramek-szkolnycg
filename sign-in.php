<?php
session_start();
@session_destroy();
$password_hash = '$2y$10$s2tGBr1MQEQCj73gjZc4xOuAHyAGVU4OnLzSXxbCv6EygOfeZYYSe';
if (isset($_POST['p'])/* && password_verify($_POST['p'], $password_hash)*/) {
    session_start();
    $_SESSION['signed_in'] = true;
    session_commit();

    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pl" data-theme="light">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="pico.min.css">
        <link rel="icon" type="image/png" href="icon.png">
        <title>Logowanie</title>
        <style>
            body {
                font-family: Roboto;
                max-width: 360px;
                margin-left: auto;
                margin-right: auto;
            }
        </style>
    </head>
    <body>
        <div style="font-size: 48px; text-align: center; margin-top: 32px;">
    <!--:3-->
        </div>
        <form style="margin-top: 32px;" method="POST" action="">
            <label>
                Hasło:
                <input type='password' name='p'></input>
            </label>
            <input type="submit" value="Zaloguj"></input>
        </form>
    </body>
</html>