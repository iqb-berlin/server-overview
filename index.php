<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Testcenter Status</title>
  <style>
    * {
      font-family: sans-serif;
      font-size: large;
    }
    td {
      padding: 10px;
      border: 1px solid silver;
    }
    a, a:visited, .tc_nice {
      color: black;
      text-shadow: 1px 1px 10px #fff, 1px 1px 10px #ccc;
    }
    .error {
      text-shadow: 1px 1px 10px red, 1px 1px 10px darkred;
    }
  </style>
</head>
<body>
  <?php $config = json_decode(file_get_contents('config.json')); ?>
  <h1>Übersicht Server</h1>
  <table>
    <thead>
      <tr>
        <td>Anwendung</td>
        <td>Url</td>
        <td>Titel</td>
        <td>Version</td>
      </tr>
    </thead>
    <tbody id="instances">
      <?php
          error_reporting(E_ALL);
          ini_set('display_errors', 1);

          function fetch(string $url, array $headers = [], bool $json = true): object | string {
            $curl = curl_init();
            curl_setopt_array($curl, [
              CURLOPT_URL => $url,
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => "",
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 5,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_HEADER => false,
              CURLOPT_FAILONERROR => false, // allows to read body on error
              CURLOPT_SSL_VERIFYHOST => 0,
              CURLOPT_SSL_VERIFYPEER => false,
              CURLOPT_VERBOSE => 1,
              CURLOPT_HTTPHEADER => $headers,
            ]);

            $curlResponse = curl_exec($curl);
            $errorCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if (($errorCode === 0) or ($curlResponse === false)) {
              return (object) ['error' => "not available", 'code' => $errorCode];
            }

            if ($errorCode >= 400) {
              return (object) ['error' => $curlResponse, 'code' => $errorCode];
            }

            return $json ? json_decode($curlResponse) : $curlResponse;
          }

          function splitVersion(string $versionString): array {
            $objectVersionParts = preg_split("/[.-]/", $versionString);

            return [
              (int) $objectVersionParts[0],
              isset($objectVersionParts[1]) ? (int) $objectVersionParts[1] : 0,
              isset($objectVersionParts[2]) ? (int) $objectVersionParts[2] : 0,
              $objectVersionParts[3] ?? ""
            ];
          }

          function colorVersion(int $a, int $b, int $c): string {
            $rgb = [0, 0, 0];
            $rgb[($a + 2) % 3] = ($b * 100) % 256;
            $rgb[$a % 3] = ($c * 30) % 256;
            return "rgba($rgb[0], $rgb[1], $rgb[2], 0.6)";
          }

          function getTestcenter(string $url): array {
            $r = fetch("$url/api/system/config");
            if (!isset($r->error)) {
              return [
                'title' => $r->appConfig->appTitle,
                'version' => $r->version,
                'background' => $r->appConfig->backgroundBody
              ];
            }
            return [
              'error' => $r->error,
              'code' => $r->code
            ];
          }

          function getStudio(string $url): array {
            $return = [
              'title' => 'unknown',
              'version' => 'unknown',
              'background' => 'white'
            ];

            $r = fetch("$url", [], false);
            if (isset($r->error)) {
              return [
                'error' => $r->error,
                'code' => $r->code
              ];
            }
            $re = '/src="(main-\S+.js)"/m';
            preg_match($re, $r, $matches);

            if (!isset($matches[1])) {
              return [
                'error' => 'could not get main js filename from index.html',
                'code' => ''
              ];
            }
            $jsFileName = $matches[1];

            $re = '/--st-body-background:\s*([^;]+);/m';
            preg_match($re, $r, $matches);
            $return['background'] = isset($matches[1]) ? $matches[1] : 'silver';


            $r = fetch("$url/$jsFileName", [], false);
            if (isset($r->error)) {
              return [
                'error' => $r->error,
                'code' => $r->code
              ];
            }

            $re = '/provide:\s*"APP_VERSION",\s*useValue:\s*"([^\"]+)"/m';
            preg_match($re, $r, $matches2);

            if (!isset($matches2[1])) {
              return [
                'error' => 'could not get version from main js',
                'code' => ''
              ];
            }

            $return['version'] = $matches2[1];

            $r = fetch("$url/api/admin/settings/config", ["App-Version: {$return['version']}"]);
            if (isset($r->error)) {
              return [
                'error' => $r->error,
                'code' => $r->code
              ];
            }

            $return['title'] = $r->appTitle;

            return $return;
          }


          foreach ($config as $url => $expectedApp) {
            echo "";
            $d = match ($expectedApp) {
              'testcenter' => getTestcenter($url),
              'studio' => getStudio($url)
            };
            if (!isset($d['error'])) {
              $versionParts = splitVersion($d['version']);
              $color = colorVersion(...$versionParts);
              echo "<tr class='ok'>";
              echo "<td><h2>$expectedApp</h2></td>";
              echo "<td style='background: {$d['background']}'><a href='$url'>$url</a></td>";
              echo "<td class='tc_nice' style='background: {$d['background']}'>{$d['title']}</td>";
              echo "<td style='background: $color'>{$d['version']}</td>";
              echo "</tr>";
            } else {
              echo "<tr class='error'>";
              echo "<td><h2>$expectedApp</h2></td>";
              echo "<td><a href='$url'>$url</a></td>";
              echo "<td>Error ({$d['code']})</td>";
              echo "<td>{$d['error']}</td>";
              echo "</tr>";
            }

            echo "</tr>";
          }
      ?>
    </tbody>
  </table>
</body>