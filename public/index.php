<?php

require_once __DIR__ . '/../config/database.php';

$error = '';
$lessons = [];

try {

    /*
    |--------------------------------------------------------------------------
    | Buscar aulas
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            id,
            lesson_number,
            title,
            duration_seconds,
            audio_path
        FROM lessons
        ORDER BY lesson_number ASC
    ");

    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $error = $e->getMessage();

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

    <title>Curso de Inglês</title>


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

            max-width: 900px;

            margin: 50px auto;

            padding: 20px;

        }


        /*
        |--------------------------------------------------------------------------
        | Cabeçalho
        |--------------------------------------------------------------------------
        */

        .header {

            background: white;

            padding: 30px;

            border-radius: 12px;

            margin-bottom: 25px;

            box-shadow:
                0 2px 10px rgba(0,0,0,.08);

        }


        .header h1 {

            margin: 0 0 10px 0;

            font-size: 32px;

        }


        .header p {

            margin: 0;

            color: #666;

            font-size: 16px;

        }


        /*
        |--------------------------------------------------------------------------
        | Card principal
        |--------------------------------------------------------------------------
        */

        .card {

            background: white;

            padding: 30px;

            border-radius: 12px;

            box-shadow:
                0 2px 10px rgba(0,0,0,.08);

        }


        .card h2 {

            margin-top: 0;

            margin-bottom: 20px;

        }


        /*
        |--------------------------------------------------------------------------
        | Menu de aulas
        |--------------------------------------------------------------------------
        */

        .lesson-select {

            width: 100%;

            padding: 15px;

            font-size: 17px;

            border: 1px solid #ccc;

            border-radius: 8px;

            background: white;

            cursor: pointer;

        }


        .lesson-select:focus {

            outline: none;

            border-color: #4d7cff;

            box-shadow:
                0 0 0 3px rgba(77,124,255,.12);

        }


        /*
        |--------------------------------------------------------------------------
        | Botão
        |--------------------------------------------------------------------------
        */

        .button {

            display: inline-block;

            width: 100%;

            margin-top: 20px;

            padding: 15px;

            border: none;

            border-radius: 8px;

            background: #4d7cff;

            color: white;

            font-size: 17px;

            font-weight: bold;

            cursor: pointer;

            text-align: center;

        }


        .button:hover {

            background: #3b68e8;

        }


        /*
        |--------------------------------------------------------------------------
        | Informações
        |--------------------------------------------------------------------------
        */

        .info {

            margin-top: 20px;

            padding: 15px;

            background: #f5f7fa;

            border-radius: 8px;

            color: #666;

            font-size: 14px;

        }


        /*
        |--------------------------------------------------------------------------
        | Erro
        |--------------------------------------------------------------------------
        */

        .error {

            background: #ffe1e1;

            border: 1px solid #ffbaba;

            color: #a00000;

            padding: 15px;

            border-radius: 8px;

        }


        /*
        |--------------------------------------------------------------------------
        | Rodapé
        |--------------------------------------------------------------------------
        */

        .footer {

            text-align: center;

            margin-top: 25px;

            color: #999;

            font-size: 13px;

        }


        /*
        |--------------------------------------------------------------------------
        | Responsivo
        |--------------------------------------------------------------------------
        */

        @media (max-width: 700px) {

            .container {

                margin: 20px auto;

                padding: 15px;

            }


            .header {

                padding: 22px;

            }


            .header h1 {

                font-size: 26px;

            }


            .card {

                padding: 22px;

            }

        }

    </style>

</head>


<body>


<div class="container">


    <!-- ==========================================================
         CABEÇALHO
         ========================================================== -->

    <div class="header">

        <h1>
            Curso de Inglês
        </h1>

        <p>
            Selecione uma aula para começar seus estudos.
        </p>

    </div>


    <!-- ==========================================================
         CONTEÚDO
         ========================================================== -->

    <?php if ($error): ?>

        <div class="error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php else: ?>


        <div class="card">


            <h2>
                Escolha a aula
            </h2>


            <?php if (empty($lessons)): ?>

                <div class="info">

                    Nenhuma aula cadastrada ainda.

                </div>

            <?php else: ?>


                <form
                    method="GET"
                    action="player_lesson.php"
                    id="lessonForm"
                >


                    <select
                        name="lesson_id"
                        id="lesson_id"
                        class="lesson-select"
                        required
                    >


                        <option value="">

                            Selecione uma aula...

                        </option>


                        <?php foreach ($lessons as $lesson): ?>

                            <option
                                value="<?= (int) $lesson['id'] ?>"
                            >

                                Lesson
                                <?= htmlspecialchars(
                                    $lesson['lesson_number']
                                ) ?>

                                —

                                <?= htmlspecialchars(
                                    $lesson['title']
                                ) ?>

                            </option>

                        <?php endforeach; ?>


                    </select>


                    <button
                        type="submit"
                        class="button"
                    >

                        ▶ Iniciar aula

                    </button>


                </form>


                <div class="info">

                    <?= count($lessons) ?>

                    aulas disponíveis.

                    <br>

                    Escolha uma aula acima para abrir o
                    player com áudio e legenda sincronizada.

                </div>


            <?php endif; ?>


        </div>


    <?php endif; ?>


    <!-- ==========================================================
         RODAPÉ
         ========================================================== -->

    <div class="footer">

        Curso de Inglês

    </div>


</div>


</body>

</html>