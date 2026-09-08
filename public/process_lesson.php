<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$message = '';
$messageType = 'info';
$selectedLessonId = (int) ($_POST['lesson_id'] ?? 0);
$importResult = null;

/**
 * Escapa texto para HTML.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Converte uma marcação de tempo SRT para segundos.
 *
 * Exemplo:
 * 00:01:23,450
 * retorna:
 * 83.450
 */
function srtTimeToSeconds(
    int $hours,
    int $minutes,
    int $seconds,
    int $milliseconds
): float {
    return
        ($hours * 3600) +
        ($minutes * 60) +
        $seconds +
        ($milliseconds / 1000);
}

/**
 * Faz o parse do conteúdo SRT.
 */
function parseSrt(string $content): array
{
    // Remove BOM UTF-8, caso exista
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

    // Normaliza quebras de linha
    $content = str_replace(["\r\n", "\r"], "\n", $content);

    $content = trim($content);

    if ($content === '') {
        return [
            'segments' => [],
            'invalid' => 0
        ];
    }

    // Divide o SRT em blocos
    $blocks = preg_split("/\n\s*\n/", $content) ?: [];

    $segments = [];
    $invalid = 0;

    $timePattern =
        '/(\d{2}):(\d{2}):(\d{2})[,.](\d{3})\s*-->\s*' .
        '(\d{2}):(\d{2}):(\d{2})[,.](\d{3})/';

    foreach ($blocks as $blockIndex => $block) {

        $block = trim($block);

        if ($block === '') {
            continue;
        }

        $lines = preg_split('/\n/', $block) ?: [];

        $lines = array_map('trim', $lines);

        if (count($lines) < 2) {
            $invalid++;
            continue;
        }

        $timeLineIndex = null;
        $matches = [];

        // Procura a linha de tempo
        foreach ($lines as $index => $line) {

            if (
                preg_match(
                    $timePattern,
                    $line,
                    $matches
                )
            ) {
                $timeLineIndex = $index;
                break;
            }
        }

        if ($timeLineIndex === null) {
            $invalid++;
            continue;
        }

        // Número da legenda
        $sequenceNumber = $blockIndex + 1;

        if (
            $timeLineIndex > 0 &&
            ctype_digit(trim($lines[0]))
        ) {
            $sequenceNumber = (int) trim($lines[0]);
        }

        // Tempos
        $startTime = srtTimeToSeconds(
            (int) $matches[1],
            (int) $matches[2],
            (int) $matches[3],
            (int) $matches[4]
        );

        $endTime = srtTimeToSeconds(
            (int) $matches[5],
            (int) $matches[6],
            (int) $matches[7],
            (int) $matches[8]
        );

        if ($endTime <= $startTime) {
            $invalid++;
            continue;
        }

        // Texto da legenda
        $textLines = array_slice(
            $lines,
            $timeLineIndex + 1
        );

        $text = implode(' ', $textLines);

        // Remove espaços duplicados
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        $text = trim($text);

        if ($text === '') {
            $invalid++;
            continue;
        }

        // Remove algumas tags comuns de formatação do SRT
        $text = preg_replace(
            '/<\/?(i|b|u|font|span)(?:\s[^>]*)?>/i',
            '',
            $text
        ) ?? $text;

        $segments[] = [
            'sequence_number' => $sequenceNumber,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'text' => $text
        ];
    }

    return [
        'segments' => $segments,
        'invalid' => $invalid
    ];
}


/*
|--------------------------------------------------------------------------
| PROCESSAMENTO
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $srtContent = trim($_POST['srt_content'] ?? '');

    if ($selectedLessonId <= 0) {

        $message = 'Selecione uma aula.';
        $messageType = 'error';

    } elseif ($srtContent === '') {

        $message = 'Cole o conteúdo do SRT.';
        $messageType = 'error';

    } else {

        try {

            /*
             * Verifica se a aula existe
             */
            $stmtLesson = $pdo->prepare(
                'SELECT id, lesson_number, title
                 FROM lessons
                 WHERE id = ?'
            );

            $stmtLesson->execute([
                $selectedLessonId
            ]);

            $lesson = $stmtLesson->fetch(PDO::FETCH_ASSOC);

            if (!$lesson) {

                throw new RuntimeException(
                    'A aula selecionada não foi encontrada.'
                );
            }


            /*
             * Faz o parse do SRT
             */
            $parsed = parseSrt($srtContent);

            $segments = $parsed['segments'];
            $invalid = $parsed['invalid'];

            if (count($segments) === 0) {

                throw new RuntimeException(
                    'Nenhum segmento válido foi encontrado no SRT.'
                );
            }


            /*
             * Inicia transação
             */
            $pdo->beginTransaction();


            /*
             * IMPORTANTE:
             *
             * Os segmentos da aula são derivados do SRT.
             * Portanto, apagamos os antigos e recriamos.
             */
            $deleteStmt = $pdo->prepare(
                'DELETE FROM lesson_segments
                 WHERE lesson_id = ?'
            );

            $deleteStmt->execute([
                $selectedLessonId
            ]);


            /*
             * Insere os novos segmentos
             *
             * speaker_id fica NULL propositalmente.
             */
            $insertStmt = $pdo->prepare(
                'INSERT INTO lesson_segments
                (
                    lesson_id,
                    speaker_id,
                    sequence_number,
                    start_time,
                    end_time,
                    text
                )
                VALUES
                (
                    ?,
                    NULL,
                    ?,
                    ?,
                    ?,
                    ?
                )'
            );


            $maxEndTime = 0;

            foreach ($segments as $segment) {

                $insertStmt->execute([
                    $selectedLessonId,
                    $segment['sequence_number'],
                    $segment['start_time'],
                    $segment['end_time'],
                    $segment['text']
                ]);

                if ($segment['end_time'] > $maxEndTime) {
                    $maxEndTime = $segment['end_time'];
                }
            }


            /*
             * Atualiza a duração da aula com base
             * no último tempo encontrado no SRT.
             */
            $durationSeconds = (int) ceil($maxEndTime);

            $updateLessonStmt = $pdo->prepare(
                'UPDATE lessons
                 SET duration_seconds = ?
                 WHERE id = ?'
            );

            $updateLessonStmt->execute([
                $durationSeconds,
                $selectedLessonId
            ]);


            /*
             * Confirma tudo
             */
            $pdo->commit();


            /*
             * Resultado
             */
            $importResult = [
                'lesson' => $lesson,
                'total' => count($segments),
                'invalid' => $invalid,
                'duration' => $durationSeconds,
                'first' => $segments[0],
                'last' => $segments[count($segments) - 1]
            ];

            $message =
                'SRT processado com sucesso! ' .
                count($segments) .
                ' segmentos foram importados.';

            $messageType = 'success';


        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message =
                'Erro ao processar o SRT: ' .
                $e->getMessage();

            $messageType = 'error';
        }
    }
}


/*
|--------------------------------------------------------------------------
| CARREGA AS AULAS
|--------------------------------------------------------------------------
*/

$stmtLessons = $pdo->query(
    'SELECT id, lesson_number, title
     FROM lessons
     ORDER BY lesson_number'
);

$lessons = $stmtLessons->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Processar SRT</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
        }

        h1 {
            margin-bottom: 10px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
        }

        select,
        textarea,
        button {
            width: 100%;
            box-sizing: border-box;
            font-size: 16px;
        }

        select {
            padding: 12px;
            margin-bottom: 20px;
        }

        textarea {
            min-height: 450px;
            padding: 12px;
            font-family: Consolas, monospace;
            resize: vertical;
        }

        button {
            margin-top: 15px;
            padding: 14px;
            border: 0;
            border-radius: 6px;
            background: #2563eb;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .info {
            background: #dbeafe;
            color: #1e40af;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 20px;
        }

        .stat {
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .stat strong {
            display: block;
            font-size: 24px;
            margin-top: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f1f5f9;
        }

        .mono {
            font-family: Consolas, monospace;
        }

        .warning {
            background: #fff7ed;
            color: #9a3412;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

    </style>

</head>

<body>

<div class="container">

    <h1>Processar SRT</h1>

    <p>
        Nesta versão, o <strong>SRT é a fonte oficial dos segmentos</strong>.
        Os tempos e textos serão importados diretamente dele.
    </p>


    <?php if ($message !== ''): ?>

        <div class="message <?= e($messageType) ?>">
            <?= e($message) ?>
        </div>

    <?php endif; ?>


    <div class="warning">

        <strong>Atenção:</strong>

        ao processar uma aula, os segmentos antigos dessa aula
        serão apagados e recriados a partir do SRT.

        O campo <code>speaker_id</code> permanecerá
        <strong>NULL</strong> nesta versão.

    </div>


    <div class="card">

        <form method="POST">

            <label for="lesson_id">
                Aula
            </label>

            <select
                name="lesson_id"
                id="lesson_id"
                required
            >

                <option value="">
                    -- Selecione uma aula --
                </option>

                <?php foreach ($lessons as $lesson): ?>

                    <option
                        value="<?= (int) $lesson['id'] ?>"
                        <?= (
                            $selectedLessonId === (int) $lesson['id']
                        ) ? 'selected' : '' ?>
                    >

                        Aula <?= (int) $lesson['lesson_number'] ?>
                        -
                        <?= e($lesson['title']) ?>

                    </option>

                <?php endforeach; ?>

            </select>


            <label for="srt_content">
                Conteúdo SRT
            </label>

            <textarea
                name="srt_content"
                id="srt_content"
                placeholder="Cole aqui o conteúdo completo do arquivo SRT..."
                required
            ><?= e($_POST['srt_content'] ?? '') ?></textarea>


            <button type="submit">
                Processar SRT
            </button>

        </form>

    </div>


    <?php if ($importResult !== null): ?>

        <div class="card">

            <h2>Resultado da importação</h2>

            <p>
                <strong>Aula:</strong>
                Aula <?= (int) $importResult['lesson']['lesson_number'] ?>
                -
                <?= e($importResult['lesson']['title']) ?>
            </p>


            <div class="stats">

                <div class="stat">

                    Segmentos importados

                    <strong>
                        <?= (int) $importResult['total'] ?>
                    </strong>

                </div>


                <div class="stat">

                    Blocos inválidos

                    <strong>
                        <?= (int) $importResult['invalid'] ?>
                    </strong>

                </div>


                <div class="stat">

                    Duração

                    <strong>
                        <?= gmdate(
                            'H:i:s',
                            (int) $importResult['duration']
                        ) ?>
                    </strong>

                </div>

            </div>


            <h3>Primeiro segmento</h3>

            <table>

                <tr>
                    <th>Sequência</th>
                    <th>Início</th>
                    <th>Fim</th>
                    <th>Speaker</th>
                    <th>Texto</th>
                </tr>

                <tr>

                    <td>
                        <?= (int) $importResult['first']['sequence_number'] ?>
                    </td>

                    <td class="mono">
                        <?= number_format(
                            $importResult['first']['start_time'],
                            3,
                            '.',
                            ''
                        ) ?>
                    </td>

                    <td class="mono">
                        <?= number_format(
                            $importResult['first']['end_time'],
                            3,
                            '.',
                            ''
                        ) ?>
                    </td>

                    <td>
                        —
                    </td>

                    <td>
                        <?= e($importResult['first']['text']) ?>
                    </td>

                </tr>

            </table>


            <h3>Último segmento</h3>

            <table>

                <tr>
                    <th>Sequência</th>
                    <th>Início</th>
                    <th>Fim</th>
                    <th>Speaker</th>
                    <th>Texto</th>
                </tr>

                <tr>

                    <td>
                        <?= (int) $importResult['last']['sequence_number'] ?>
                    </td>

                    <td class="mono">
                        <?= number_format(
                            $importResult['last']['start_time'],
                            3,
                            '.',
                            ''
                        ) ?>
                    </td>

                    <td class="mono">
                        <?= number_format(
                            $importResult['last']['end_time'],
                            3,
                            '.',
                            ''
                        ) ?>
                    </td>

                    <td>
                        —
                    </td>

                    <td>
                        <?= e($importResult['last']['text']) ?>
                    </td>

                </tr>

            </table>

        </div>

    <?php endif; ?>

</div>

</body>

</html>