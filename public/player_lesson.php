<?php

require_once __DIR__ . '/../config/database.php';

$lessonId = isset($_GET['lesson_id'])
    ? (int) $_GET['lesson_id']
    : 1;

$error = '';
$lesson = null;
$segments = [];

try {

    /*
    |--------------------------------------------------------------------------
    | Buscar aula
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            lesson_number,
            title,
            duration_seconds,
            audio_path
        FROM lessons
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$lessonId]);

    $lesson = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lesson) {
        throw new Exception('Aula não encontrada.');
    }


    /*
    |--------------------------------------------------------------------------
    | Buscar segmentos SRT
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            speaker_id,
            sequence_number,
            start_time,
            end_time,
            text
        FROM lesson_segments
        WHERE lesson_id = ?
        ORDER BY sequence_number ASC
    ");

    $stmt->execute([$lessonId]);

    $segments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($segments)) {
        throw new Exception(
            'Nenhum segmento SRT encontrado para esta aula.'
        );
    }

} catch (Throwable $e) {

    $error = $e->getMessage();

}


/*
|--------------------------------------------------------------------------
| Caminho do áudio
|--------------------------------------------------------------------------
|
| Por enquanto usamos audio_path da tabela lessons.
|
*/

$audioPath = '';

if ($lesson && !empty($lesson['audio_path'])) {

    $audioPath = $lesson['audio_path'];

}

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= $lesson
            ? 'Lesson ' . htmlspecialchars($lesson['lesson_number'])
            : 'Player'
        ?>
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f6f8;

            color: #222;

        }


        .container {

            max-width: 1100px;

            margin: 30px auto;

            padding: 20px;

        }


        .header {

            background: white;

            padding: 20px;

            border-radius: 10px;

            margin-bottom: 20px;

            box-shadow:
                0 2px 8px rgba(0,0,0,.08);

        }


        .header h1 {

            margin: 0 0 8px 0;

        }


        .header p {

            margin: 0;

            color: #666;

        }


        .player {

            background: white;

            padding: 20px;

            border-radius: 10px;

            margin-bottom: 20px;

            box-shadow:
                0 2px 8px rgba(0,0,0,.08);

        }


        audio {

            width: 100%;

            margin-bottom: 15px;

        }


        .current {

            min-height: 120px;

            display: flex;

            align-items: center;

            justify-content: center;

            text-align: center;

            padding: 20px;

            border-radius: 10px;

            background: #eef4ff;

            border: 2px solid #d7e3ff;

        }


        .current-text {

            font-size: 25px;

            line-height: 1.5;

            font-weight: bold;

        }


        .current-speaker {

            font-size: 14px;

            color: #666;

            margin-bottom: 8px;

        }


        .empty-current {

            color: #999;

            font-size: 18px;

        }


        .segments {

            background: white;

            border-radius: 10px;

            overflow: hidden;

            box-shadow:
                0 2px 8px rgba(0,0,0,.08);

        }


        .segments-header {

            padding: 18px 20px;

            border-bottom: 1px solid #ddd;

        }


        .segments-header h2 {

            margin: 0;

        }


        .segment {

            display: grid;

            grid-template-columns: 70px 150px 1fr;

            gap: 15px;

            padding: 13px 20px;

            border-bottom: 1px solid #eee;

            cursor: pointer;

            transition: background .15s;

        }


        .segment:hover {

            background: #f5f8ff;

        }


        .segment.active {

            background: #dfeaff;

        }


        .segment-number {

            font-weight: bold;

            color: #666;

        }


        .segment-time {

            font-family: monospace;

            color: #666;

        }


        .segment-text {

            line-height: 1.5;

        }


        .error {

            background: #ffe1e1;

            border: 1px solid #ffbaba;

            color: #a00000;

            padding: 15px;

            border-radius: 8px;

        }


        .info {

            margin-top: 10px;

            color: #666;

            font-size: 14px;

        }


        @media (max-width: 700px) {

            .segment {

                grid-template-columns: 50px 110px 1fr;

                gap: 8px;

                padding: 10px;

            }


            .current-text {

                font-size: 20px;

            }

        }

    </style>

</head>


<body>


<div class="container">


    <?php if ($error): ?>

        <div class="error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php else: ?>


        <!-- ======================================================
             CABEÇALHO
             ====================================================== -->

        <div class="header">

            <h1>

                Lesson
                <?= htmlspecialchars(
                    $lesson['lesson_number']
                ) ?>

                —

                <?= htmlspecialchars(
                    $lesson['title']
                ) ?>

            </h1>

            <p>

                Player de áudio com SRT sincronizado

            </p>

        </div>


        <!-- ======================================================
             PLAYER
             ====================================================== -->

        <div class="player">


            <?php if ($audioPath): ?>

                <audio
                    id="audio"
                    controls
                    preload="metadata"
                >

                    <source
                        src="<?= htmlspecialchars($audioPath) ?>"
                        type="audio/mpeg"
                    >

                    Seu navegador não suporta áudio.

                </audio>


            <?php else: ?>

                <div class="error">

                    O campo
                    <strong>audio_path</strong>
                    desta aula ainda está vazio.

                    <div class="info">

                        Primeiro vamos cadastrar o caminho do
                        arquivo de áudio no banco.

                    </div>

                </div>

            <?php endif; ?>


            <!-- ==================================================
                 LEGENDA ATUAL
                 ================================================== -->

            <div
                id="current"
                class="current"
            >

                <div class="empty-current">

                    ▶ Inicie o áudio para começar

                </div>

            </div>


        </div>


        <!-- ======================================================
             SEGMENTOS
             ====================================================== -->

        <div class="segments">


            <div class="segments-header">

                <h2>

                    Legenda da aula

                </h2>

                <div class="info">

                    <?= count($segments) ?>
                    segmentos SRT

                </div>

            </div>


            <div id="segmentList">


                <?php foreach ($segments as $index => $segment): ?>

                    <div
                        class="segment"
                        data-index="<?= $index ?>"
                        data-start="<?= htmlspecialchars(
                            $segment['start_time']
                        ) ?>"
                        data-end="<?= htmlspecialchars(
                            $segment['end_time']
                        ) ?>"
                    >


                        <div class="segment-number">

                            #
                            <?= htmlspecialchars(
                                $segment['sequence_number']
                            ) ?>

                        </div>


                        <div class="segment-time">

                            <?= number_format(
                                (float) $segment['start_time'],
                                3,
                                ',',
                                ''
                            ) ?>

                            →

                            <?= number_format(
                                (float) $segment['end_time'],
                                3,
                                ',',
                                ''
                            ) ?>

                        </div>


                        <div class="segment-text">

                            <?= htmlspecialchars(
                                $segment['text']
                            ) ?>

                        </div>


                    </div>

                <?php endforeach; ?>


            </div>

        </div>


    <?php endif; ?>


</div>


<?php if (!$error): ?>


<script>

    /*
    |--------------------------------------------------------------------------
    | Dados dos segmentos vindos do PHP
    |--------------------------------------------------------------------------
    */

    const segments = <?= json_encode(
        $segments,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    ) ?>;


    const audio =
        document.getElementById('audio');


    const current =
        document.getElementById('current');


    const segmentElements =
        document.querySelectorAll('.segment');


    /*
    |--------------------------------------------------------------------------
    | Segmento atualmente ativo
    |--------------------------------------------------------------------------
    */

    let currentIndex = -1;


    /*
    |--------------------------------------------------------------------------
    | Formatar tempo
    |--------------------------------------------------------------------------
    */

    function formatTime(seconds) {

        seconds = Number(seconds);

        if (!Number.isFinite(seconds)) {

            return '0:00';

        }


        const minutes =
            Math.floor(seconds / 60);


        const secs =
            Math.floor(seconds % 60);


        return (
            minutes +
            ':' +
            String(secs).padStart(2, '0')
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Encontrar segmento pelo tempo do áudio
    |--------------------------------------------------------------------------
    */

    function findSegmentIndex(time) {

        for (
            let i = 0;
            i < segments.length;
            i++
        ) {

            const start =
                Number(segments[i].start_time);


            const end =
                Number(segments[i].end_time);


            if (
                time >= start &&
                time < end
            ) {

                return i;

            }

        }


        return -1;

    }


    /*
    |--------------------------------------------------------------------------
    | Atualizar legenda
    |--------------------------------------------------------------------------
    */

    function updateSubtitle() {

        if (!audio) {

            return;

        }


        const time =
            audio.currentTime;


        const index =
            findSegmentIndex(time);


        /*
        |--------------------------------------------------------------------------
        | Nenhum segmento neste momento
        |--------------------------------------------------------------------------
        */

        if (index === -1) {

            if (currentIndex !== -1) {

                segmentElements[
                    currentIndex
                ].classList.remove('active');

                currentIndex = -1;

            }


            current.innerHTML = `

                <div class="empty-current">

                    Nenhuma fala neste momento

                </div>

            `;


            return;

        }


        /*
        |--------------------------------------------------------------------------
        | Evita redesenhar se continuarmos no mesmo segmento
        |--------------------------------------------------------------------------
        */

        if (index === currentIndex) {

            return;

        }


        /*
        |--------------------------------------------------------------------------
        | Remove destaque anterior
        |--------------------------------------------------------------------------
        */

        if (currentIndex !== -1) {

            segmentElements[
                currentIndex
            ].classList.remove('active');

        }


        currentIndex = index;


        /*
        |--------------------------------------------------------------------------
        | Destaca segmento atual
        |--------------------------------------------------------------------------
        */

        const element =
            segmentElements[index];


        element.classList.add('active');


        /*
        |--------------------------------------------------------------------------
        | Scroll automático
        |--------------------------------------------------------------------------
        */

        element.scrollIntoView({

            behavior: 'smooth',

            block: 'center'

        });


        /*
        |--------------------------------------------------------------------------
        | Texto atual
        |--------------------------------------------------------------------------
        */

        const segment =
            segments[index];


        current.innerHTML = `

            <div>

                <div class="current-speaker">

                    Speaker
                    ${segment.speaker_id ?? ''}

                    &nbsp; • &nbsp;

                    ${formatTime(segment.start_time)}

                    →
                    ${formatTime(segment.end_time)}

                </div>

                <div class="current-text">

                    ${escapeHtml(segment.text)}

                </div>

            </div>

        `;

    }


    /*
    |--------------------------------------------------------------------------
    | Segurança para texto vindo do banco
    |--------------------------------------------------------------------------
    */

    function escapeHtml(text) {

        const div =
            document.createElement('div');

        div.textContent = text;

        return div.innerHTML;

    }


    /*
    |--------------------------------------------------------------------------
    | Sincronização
    |--------------------------------------------------------------------------
    */

    if (audio) {

        audio.addEventListener(
            'timeupdate',
            updateSubtitle
        );


        audio.addEventListener(
            'seeked',
            updateSubtitle
        );


        audio.addEventListener(
            'loadedmetadata',
            updateSubtitle
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Clique em uma legenda
    |--------------------------------------------------------------------------
    */

    segmentElements.forEach(
        (element, index) => {

            element.addEventListener(
                'click',
                () => {

                    if (!audio) {

                        return;

                    }


                    const start =
                        Number(
                            segments[index].start_time
                        );


                    audio.currentTime =
                        start;


                    updateSubtitle();


                    /*
                    | Se o áudio estiver pausado,
                    | não iniciamos automaticamente.
                    */

                }
            );

        }
    );

</script>


<?php endif; ?>


</body>

</html>