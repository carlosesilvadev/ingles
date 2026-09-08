<?php

require_once __DIR__ . '/../config/database.php';

$message = '';
$message_type = '';
$result = [];

/*
|--------------------------------------------------------------------------
| Funções auxiliares
|--------------------------------------------------------------------------
*/

function timeToSeconds($time)
{
    $time = str_replace(',', '.', trim($time));

    $parts = explode(':', $time);

    if (count($parts) !== 3) {
        return null;
    }

    $hours = (int) $parts[0];
    $minutes = (int) $parts[1];
    $seconds = (float) $parts[2];

    return ($hours * 3600) + ($minutes * 60) + $seconds;
}


/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $lesson_id = intval($_POST['lesson_id'] ?? 0);

    $txt_content = trim($_POST['txt_content'] ?? '');

    $srt_content = trim($_POST['srt_content'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | Validações
    |--------------------------------------------------------------------------
    */

    if (!$lesson_id) {

        $message = 'Selecione uma aula.';
        $message_type = 'error';

    } elseif (!$txt_content) {

        $message = 'Cole o conteúdo TXT.';
        $message_type = 'error';

    } elseif (!$srt_content) {

        $message = 'Cole o conteúdo SRT.';
        $message_type = 'error';

    } else {


        /*
        |--------------------------------------------------------------------------
        | 1. PROCESSA TXT
        |--------------------------------------------------------------------------
        */

        $txt_lines = preg_split('/\R/', $txt_content);

        $txt_segments = [];

        $current_speaker = null;

        $current_text = '';


        foreach ($txt_lines as $line) {

            $line = trim($line);

            if ($line === '') {
                continue;
            }


            /*
            | Procura:
            | Speaker A:
            | Speaker B:
            | Speaker C:
            */

            if (
                preg_match(
                    '/^Speaker\s+([A-Z0-9]+)\s*:\s*(.*)$/i',
                    $line,
                    $matches
                )
            ) {

                /*
                | Salva a fala anterior
                */

                if (
                    $current_speaker !== null &&
                    trim($current_text) !== ''
                ) {

                    $txt_segments[] = [

                        'speaker' => strtoupper($current_speaker),

                        'text' => trim($current_text)

                    ];
                }


                /*
                | Inicia nova fala
                */

                $current_speaker = strtoupper($matches[1]);

                $current_text = $matches[2];


            } else {

                /*
                | Continuação da fala anterior
                */

                if ($current_speaker !== null) {

                    $current_text .= ' ' . $line;
                }
            }
        }


        /*
        | Salva última fala
        */

        if (
            $current_speaker !== null &&
            trim($current_text) !== ''
        ) {

            $txt_segments[] = [

                'speaker' => strtoupper($current_speaker),

                'text' => trim($current_text)

            ];
        }


        /*
        |--------------------------------------------------------------------------
        | 2. PROCESSA SRT
        |--------------------------------------------------------------------------
        */

        /*
        | Divide os blocos SRT pelas linhas vazias.
        */

        $srt_blocks = preg_split(
            '/\R\s*\R/',
            $srt_content
        );

        $srt_segments = [];


        foreach ($srt_blocks as $block) {

            $block = trim($block);

            if ($block === '') {
                continue;
            }


            $lines = preg_split(
                '/\R/',
                $block
            );


            /*
            | SRT precisa ter pelo menos:
            |
            | 1
            | 00:00:00,000 --> 00:00:02,000
            | Texto
            */

            if (count($lines) < 3) {
                continue;
            }


            $sequence = trim($lines[0]);

            $time_line = trim($lines[1]);


            /*
            |--------------------------------------------------------------------------
            | Identifica timestamp
            |--------------------------------------------------------------------------
            */

            if (
                !preg_match(
                    '/(\d{2}:\d{2}:\d{2}[,.]\d{3})\s*-->\s*(\d{2}:\d{2}:\d{2}[,.]\d{3})/',
                    $time_line,
                    $matches
                )
            ) {

                continue;
            }


            $start = timeToSeconds($matches[1]);

            $end = timeToSeconds($matches[2]);


            if ($start === null || $end === null) {
                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | Texto
            |--------------------------------------------------------------------------
            */

            $text_lines = array_slice($lines, 2);

            $text = implode(' ', $text_lines);

            $text = trim($text);


            if ($text === '') {
                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | Adiciona segmento
            |--------------------------------------------------------------------------
            */

            $srt_segments[] = [

                'sequence' => intval($sequence),

                'start' => $start,

                'end' => $end,

                'text' => $text

            ];
        }


        /*
        |--------------------------------------------------------------------------
        | 3. RESULTADO DA ANÁLISE
        |--------------------------------------------------------------------------
        */

        $result = [

            'txt_count' => count($txt_segments),

            'srt_count' => count($srt_segments),

            'txt_segments' => $txt_segments,

            'srt_segments' => $srt_segments

        ];


        /*
        |--------------------------------------------------------------------------
        | 4. IMPORTA SRT PARA lesson_segments
        |--------------------------------------------------------------------------
        */

        try {

            /*
            | Verifica se a aula existe
            */

            $stmt = $pdo->prepare(
                "SELECT id
                 FROM lessons
                 WHERE id = ?
                 LIMIT 1"
            );

            $stmt->execute([$lesson_id]);

            $lesson = $stmt->fetch(PDO::FETCH_ASSOC);


            if (!$lesson) {

                throw new Exception(
                    'A aula selecionada não existe.'
                );
            }


            /*
            | Inicia transação
            */

            $pdo->beginTransaction();


            /*
            | Remove segmentos antigos dessa aula.
            |
            | Isso permite importar novamente sem duplicar.
            */

            $delete = $pdo->prepare(
                "DELETE FROM lesson_segments
                 WHERE lesson_id = ?"
            );

            $delete->execute([$lesson_id]);


            /*
            | Prepara INSERT
            |
            | speaker_id ficará NULL por enquanto.
            */

            $insert = $pdo->prepare(
                "INSERT INTO lesson_segments
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
                )"
            );


            $imported = 0;


            foreach ($srt_segments as $segment) {

                $insert->execute([

                    $lesson_id,

                    $segment['sequence'],

                    $segment['start'],

                    $segment['end'],

                    $segment['text']

                ]);

                $imported++;
            }


            /*
            | Confirma transação
            */

            $pdo->commit();


            $message =
                "Importação concluída! "
                . $imported
                . " segmentos foram gravados na tabela lesson_segments.";

            $message_type = 'success';


        } catch (Exception $e) {


            /*
            | Se alguma coisa der errado,
            | desfaz tudo.
            */

            if ($pdo->inTransaction()) {

                $pdo->rollBack();
            }


            $message =
                'Erro ao importar: '
                . $e->getMessage();

            $message_type = 'error';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Carrega aulas
|--------------------------------------------------------------------------
*/

$lessons = $pdo->query(
    "
    SELECT
        id,
        lesson_number,
        title
    FROM lessons
    ORDER BY lesson_number
    "
)->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <title>Importar aula</title>


    <style>

        body {

            font-family: Arial, sans-serif;

            max-width: 1200px;

            margin: 40px auto;

            padding: 20px;

            background: #fafafa;
        }


        h1 {

            margin-bottom: 30px;
        }


        textarea {

            width: 100%;

            min-height: 300px;

            margin-bottom: 20px;

            padding: 12px;

            box-sizing: border-box;

            font-family: monospace;

            font-size: 14px;
        }


        select {

            padding: 10px;

            width: 300px;

            margin-bottom: 20px;

        }


        button {

            padding: 12px 24px;

            cursor: pointer;

            font-size: 16px;

        }


        .message {

            padding: 15px;

            margin-bottom: 20px;

            border-radius: 5px;

        }


        .success {

            background: #dff0d8;

            color: #2d662d;

        }


        .error {

            background: #f2dede;

            color: #a94442;

        }


        .stats {

            padding: 15px;

            background: #f5f5f5;

            margin-top: 20px;

        }


        table {

            width: 100%;

            border-collapse: collapse;

            margin-top: 20px;

            background: white;

        }


        th,
        td {

            border: 1px solid #ccc;

            padding: 8px;

            text-align: left;

        }


        th {

            background: #eee;

        }


        .speaker {

            font-weight: bold;

        }


        .section {

            margin-top: 40px;

        }

    </style>

</head>


<body>


<h1>Importar aula</h1>


<?php if ($message): ?>

    <div class="message <?= htmlspecialchars($message_type) ?>">

        <?= htmlspecialchars($message) ?>

    </div>

<?php endif; ?>


<form method="POST">


    <label>

        <strong>Aula:</strong>

    </label>

    <br>


    <select name="lesson_id" required>

        <option value="">

            Selecione uma aula

        </option>


        <?php foreach ($lessons as $lesson): ?>

            <option value="<?= $lesson['id'] ?>">

                Lesson <?= htmlspecialchars($lesson['lesson_number']) ?>

                -

                <?= htmlspecialchars($lesson['title']) ?>

            </option>

        <?php endforeach; ?>

    </select>


    <div class="section">

        <h2>TXT da aula</h2>

        <p>

            Cole aqui o TXT contendo Speaker A, Speaker B, Speaker C etc.

        </p>


        <textarea
            name="txt_content"
            placeholder="Speaker A: Esta é a lição um...

Speaker B: Excuse me, miss...

Speaker C: No, sir..."
        ></textarea>

    </div>


    <div class="section">

        <h2>SRT da aula</h2>

        <p>

            Cole aqui o arquivo SRT completo.

        </p>


        <textarea
            name="srt_content"
            placeholder="1

00:00:00,380 --> 00:00:03,440

Esta é a lição um..."
        ></textarea>

    </div>


    <button type="submit">

        Importar aula

    </button>


</form>


<?php if (!empty($result)): ?>


    <div class="stats">

        <h2>Resultado da análise</h2>


        <p>

            Segmentos encontrados no TXT:

            <strong>

                <?= $result['txt_count'] ?>

            </strong>

        </p>


        <p>

            Segmentos encontrados no SRT:

            <strong>

                <?= $result['srt_count'] ?>

            </strong>

        </p>


    </div>


    <div class="section">

        <h2>TXT identificado</h2>


        <table>

            <tr>

                <th>#</th>

                <th>Speaker</th>

                <th>Texto</th>

            </tr>


            <?php foreach (
                $result['txt_segments']
                as $index => $segment
            ): ?>

                <tr>

                    <td>

                        <?= $index + 1 ?>

                    </td>


                    <td class="speaker">

                        <?= htmlspecialchars(
                            $segment['speaker']
                        ) ?>

                    </td>


                    <td>

                        <?= htmlspecialchars(
                            $segment['text']
                        ) ?>

                    </td>

                </tr>

            <?php endforeach; ?>

        </table>

    </div>


    <div class="section">

        <h2>SRT identificado</h2>


        <table>

            <tr>

                <th>#</th>

                <th>Início</th>

                <th>Fim</th>

                <th>Texto</th>

            </tr>


            <?php foreach (
                $result['srt_segments']
                as $segment
            ): ?>

                <tr>

                    <td>

                        <?= $segment['sequence'] ?>

                    </td>


                    <td>

                        <?= $segment['start'] ?>

                    </td>


                    <td>

                        <?= $segment['end'] ?>

                    </td>


                    <td>

                        <?= htmlspecialchars(
                            $segment['text']
                        ) ?>

                    </td>

                </tr>

            <?php endforeach; ?>

        </table>

    </div>


<?php endif; ?>


</body>

</html>