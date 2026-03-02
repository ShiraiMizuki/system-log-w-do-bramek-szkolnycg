<?php

session_start();
if (!isset($_SESSION['signed_in'])) {
    header('Location: sign-in.php');
    exit;
}
$id = $_GET['id'] ?? null;
$date_start = $_GET['date_start'] ?? null;
$date_end = $_GET['date_end'] ?? null;
if (is_null($id) || is_null($date_start) || is_null($date_end)) {
    echo "Nieprawidłowe parametry.";
    exit;
}
date_default_timezone_set('Europe/Warsaw');
// proba polaczenia z prostgcostam
$niepotrzebnazmienna = "postgresql://test:foobar@192.168.0.55:5432/users";
$DaTaBaSeKoNeKtIoN = pg_connect($niepotrzebnazmienna);
if (!$DaTaBaSeKoNeKtIoN) {
    echo "Błąd połączenia z bazą danych.";
    exit;
}
$id = @filter_var($id, FILTER_SANITIZE_STRING);
$date_start = @filter_var($date_start, FILTER_SANITIZE_STRING);
$date_end = @filter_var($date_end, FILTER_SANITIZE_STRING);        
$unix_date_start = strtotime($date_start);
$unix_date_end = strtotime($date_end) + 86400;
$result = pg_query_params($DaTaBaSeKoNeKtIoN, "
    SELECT
        name
    FROM logi
    WHERE nr = $1
    LIMIT 1
", array($id));

$row = pg_fetch_assoc($result);
$name = $row['name'];
// :3
$result = pg_query_params($DaTaBaSeKoNeKtIoN, "
    SELECT
        io,
        ROUND(CAST(time AS BIGINT) / 1000) AS unix_ts
    FROM logi
    WHERE
        nr = $1
        AND CAST(time AS BIGINT) > 1743465600000
        AND ROUND(CAST(time AS BIGINT) / 1000) >= $2
        AND ROUND(CAST(time AS BIGINT) / 1000) <= $3
    ORDER BY unix_ts ASC
", array($id, $unix_date_start, $unix_date_end));
$report  = [];
$last_io = null;
$entry_unix_ts = null;
while ($row = pg_fetch_assoc($result)) {
    $unix_ts = $row['unix_ts'];
    $key = date('Y-m-d', $unix_ts);
    $h_i = date('H:i', $unix_ts);
    if (!isset($report[$key])) {
        $report[$key] = [
            'events'     => [],
            'total_time' => 0,
        ];
        $last_io = null;
        $entry_unix_ts = null;
    }
    // Pierwszym zdarzeniem w ciągu dnia nie może być wyjście:
    if (is_null($last_io) && $row['io'] > 0) {
        continue;
    }
    // Po wejściu musi nastąpić wyjście i vice-versa:   peak komentarz panie praktykant
    if (!is_null($last_io) && $last_io == $row['io']) {
        continue;
    }
    $report[$key]['events'][] = [
        'type' => $row['io'] === 0 ? 'Wejście' : 'Wyjście',
        'time' => $h_i,
    ];
    if (!is_null($entry_unix_ts) && $row['io'] > 0) {
        $diff = $unix_ts - $entry_unix_ts;
        $report[$key]['total_time'] += $diff > 0 ? $diff : 0;
    }
    if ($row['io'] == 0) {
        $entry_unix_ts = $row['unix_ts'];
    } else {
        $entry_unix_ts = null;
    }
    $last_io = $row['io'];
}                                                  //koniec
foreach ($report as $date => $data) {
    if (count($data['events']) > 0) {
        continue;
    }
    unset($report[$date]);
}
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
<!--frontendu nie tykam -->
        <style>
            @font-face {
                font-family: Roboto;
                src: url('./Roboto-Light.ttf');
            }

            body {
                font-family: Roboto;
                max-width: 620px;
                margin-left: auto;
                margin-right: auto;
            }

            .container {
                display: block;
            }

            .report {
                padding-left: 8px;
                padding-right: 8px;
                margin-top: 28px;
                max-width: 213.3px;
                break-inside: avoid;
                flex-basis: 33%;
            }

            th,td {
                padding-top: 6px !important;
                padding-bottom: 4px !important;
                padding-left: 8px !important;
                padding-right: 8px !important;
            }

            .report-container {
                display: flex;
                flex-wrap: wrap;
            }

            @media print {
                body > * {
                    display: none !important;
                }

                .printable {
                    display: block !important;
                }

                .print-hidden {
                    display: none !important;
                }
            }

            @media screen and (max-width: 768px) {
                 .report-container {
                     justify-content: center !important;
                 }
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
                            onclick="document.location='./index.php';"
                        >
                            Powrót
                        </button>
                    </li>
                </uL>
            </nav>

            <hr style="margin-top: 4px;"/>
        </header>

        <main class="container printable" style="padding-top: 0;">
            <hgroup style="text-align: center;">
                <h6>
                    Raport dla&nbsp;<mark><?=$name?></mark>
                    &nbsp;
                    <small style="opacity: 0.8;">
                        <span class="print-hidden">📅</span> Od <?=$date_start?> Do <?=$date_end?>
                    </small>
                </h6>
            </hgroup>

            <?php
                if (count($report) === 0) {
                    echo "<h5 style='text-align: center; margin-top: 64px;'>⚠️ Brak danych dla wskazanych dat.</h5>";
                }
            ?>

            <?php
                if (count($report) > 0) {
                    echo "<div class='print-hidden' style='text-align: center; margin-top: 32px;'>
                        <button
                            class='secondary outline'
                            style='border: none;'
                            onclick='window.print()'
                        >
                            🖨️ Wydruk
                        </button>
                    </div>";
                }
            ?>

            <div class="report-container" style="justify-content: <?=count($report) > 2 ? 'start' : 'center'?>;">

            <?php
                foreach ($report as $date => $data) {
                    $report_id = 'report_' . $date;

                    $total_time_h = floor($data['total_time'] / 3600);
                    $total_time_m = ceil(($data['total_time'] % 3600) / 60);

                    $incomplete_data_warning = false;

                    if (end($data['events'])['type'] !== 'Wyjście') {
                        $incomplete_data_warning = true;
                    }

                    echo "<div
                        id='{$report_id}'
                        class='report printable'
                    >";

                    echo "<h6 style='margin-bottom: 8px; text-align: center;'>{$date}</h6>";

                    echo '<table class="striped">';

                    echo "<thead data-theme='dark' class='print-hidden'>
                        <tr>
                            <th>Zdarzenie</th>
                            <th>Godzina</th>
                        </tr>
                    </thead>";

                    echo '<tbody>';

                    foreach ($data['events'] as $i => $event) {
                        echo "<tr>
                            <td>{$event['type']}</td>
                            <td>{$event['time']}</td>
                        </tr>";
                    }

                    echo '</tbody>';

                    if ($incomplete_data_warning) {
                        echo "<tfoot>";

                        echo "<tr>
                            <td colspan='3' style='text-align: center;'>
                                <span class='print-hidden'>⚠️</span>
                                b.d.
                            </th>
                        </tr>";

                        echo "</tfoot>";
                    } else {
                        echo "<tfoot>
                            <tr>
                                <td>
                                    Czas
                                </td>

                                <td>
                                    {$total_time_h}h {$total_time_m}m
                                </td>
                            </tr>
                        </tfoot>";
                    }

                    echo '</table>';
                    echo '</div>';
                }
            ?>

            </div>
        </main>
    </body>
</html>