<?php
session_start();

if (!isset($_SESSION['signed_in'])) {
    header('Location: sign-in.php');
    exit;
}
//idzie sie domyslec
$niepotrzebnazmienna = "postgresql://test:foobar@192.168.0.55:5432/users";
$DaTaBaSeKoNeKtIoN = pg_connect($niepotrzebnazmienna);

if (!$DaTaBaSeKoNeKtIoN) {
    die("Błąd połączenia z bazą danych.");
}
$result = pg_query($DaTaBaSeKoNeKtIoN, "
    SELECT nr, name, ort, COUNT(*) AS c
    FROM logi
    WHERE
        ort NOT LIKE '%Gosc%'
        AND CAST(time AS BIGINT) > 1743465600000
    GROUP BY nr, name, ort
    HAVING COUNT(*) > 1
    ORDER BY name
");
$employees = [];
while ($row = pg_fetch_assoc($result)) {
    $fq_name = "{$row['name']} ({$row['ort']}, {$row['nr']})";
    $employees[$row['nr']] = $fq_name;
}
$result = pg_query($DaTaBaSeKoNeKtIoN, "
    SELECT
        TO_CHAR(TO_TIMESTAMP(MIN(CAST(time AS BIGINT) / 1000)), 'YYYY-MM-DD') AS min_time,
        TO_CHAR(TO_TIMESTAMP(MAX(CAST(time AS BIGINT) / 1000)), 'YYYY-MM-DD') AS max_time
    FROM logi
    WHERE CAST(time AS BIGINT) > 1743465600000
");
$row = pg_fetch_assoc($result);
$min_time = $row['min_time'];
$max_time = $row['max_time'];
pg_close($DaTaBaSeKoNeKtIoN);
?>
<!DOCTYPE html>
<html lang="pl" data-theme="light">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="pico.min.css">
        <link rel="icon" type="image/png" href="icon.png">
        <title>GateKeeper</title>
        <style>
            @font-face {
                font-family: Roboto;
                src: url('./Roboto-Light.ttf');
            }

            body {
                font-family: Roboto;
                max-width: 420px;
                margin-left: auto;
                margin-right: auto;
            }
        </style>
    </head>
    <body>
        <header class="container">
            <nav>
                <ul>
                    <li style="padding-bottom: 0; padding-top: 42px;">
                        👮
                    </li>

                    <li style="padding-bottom: 0; padding-top: 48px;">
                        <b>Gate</b>Keeper
                    </li>
                </ul>

                <ul>
                    <li style="padding-bottom: 0; padding-top: 42px; margin-right: 8px;">
                        <button
                            class="outline contrast"
                            style="font-size: 12px; padding-left: 8px; padding-right: 8px; padding-top: 6px; padding-bottom: 6px;"   
                            onclick="document.location='./sign-in.php';"    
                        >
                            Wyloguj
                        </button>
                    </li>
                </uL>
            </nav>
            <!--:3-->
            <hr style="margin-top: 4px;"/>
        </header>
        <main class="container" style="padding-top: 0;">
            <form>
                <fieldset>
                    <label>
                        <b>Pracownik</b>
                        <select id="select_pracownik">
                            <?php
                                foreach ($employees as $id => $fq_name) {
                                    echo "<option value='{$id}'>{$fq_name}</option>";
                                }
                            ?>
                        </select>
                    </label>
                    <label>
                        <b>Zakres dat</b>&nbsp;&nbsp;(od - do)
                        <input
                            id="date_start"
                            type="date"
                            style="margin-bottom: 8px;"
                            min="<?=$min_time?>"
                            max="<?=$max_time?>"
                            value="<?=$min_time?>"
                            onchange="onchange_date_start(this.value);"/>
                        <input
                            id="date_end"
                            type="date"
                            min="<?=$min_time?>"
                            max="<?=$max_time?>"
                            value="<?=$max_time?>"
                            onchange="onchange_date_end(this.value);"/>
                    </label>
                    <input
                        id="submit_button"
                        type="button"
                        value="Wygeneruj Raport"
                        style="margin-top: 8px;"/>
                </fieldset>
            </form>
        </main>
    </body>

    <script>
        const submit_button = document.getElementById("submit_button");

        submit_button.onclick = function() {
            const id = document.getElementById("select_pracownik").value;
            const date_start = document.getElementById("date_start").value;
            const date_end = document.getElementById("date_end").value;
            document.location = `./report.php?id=${id}&date_start=${date_start}&date_end=${date_end}`;
        }
        function onchange_date_start(date_value) {
            const date_start_unix_ts = (new Date(date_value)).getTime();
            const date_end_unix_ts   = (new Date(document.getElementById("date_end").value)).getTime();
            // New start date is higher than end date:
            if (date_start_unix_ts > date_end_unix_ts) {
                document.getElementById("date_end").value = date_value;
            }
        }
        function onchange_date_end(date_value) {
            const date_end_unix_ts   = (new Date(date_value)).getTime();
            const date_start_unix_ts = (new Date(document.getElementById("date_start").value)).getTime();
            // New end date is smaller than start date:    this code is shit
            if (date_end_unix_ts < date_start_unix_ts) {
                document.getElementById("date_start").value = date_value;
            }
        }
    </script>
</html>