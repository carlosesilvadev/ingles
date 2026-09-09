/*Arquivo para criação do banco e também das tabelas do projeto*/

/*Criar Banco*/

CREATE DATABASE ingles
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;


/*Criar tabelas - INÍCIO*/

CREATE TABLE courses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE lessons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    lesson_number INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    type ENUM('main', 'reading') NOT NULL DEFAULT 'main',
    duration_seconds INT UNSIGNED DEFAULT NULL,
    audio_path VARCHAR(500) DEFAULT NULL,
    txt_path VARCHAR(500) DEFAULT NULL,
    srt_path VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_lessons_course
        FOREIGN KEY (course_id)
        REFERENCES courses(id)
        ON DELETE CASCADE,

    UNIQUE KEY uk_course_lesson_number (course_id, lesson_number)
);

CREATE TABLE speakers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(100) DEFAULT NULL,

    UNIQUE KEY uk_speaker_code (code)
);

CREATE TABLE lesson_segments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT UNSIGNED NOT NULL,
    speaker_id INT UNSIGNED DEFAULT NULL,
    sequence_number INT NOT NULL,
    start_time DECIMAL(10,3) NOT NULL,
    end_time DECIMAL(10,3) NOT NULL,
    text TEXT NOT NULL,

    CONSTRAINT fk_segments_lesson
        FOREIGN KEY (lesson_id)
        REFERENCES lessons(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_segments_speaker
        FOREIGN KEY (speaker_id)
        REFERENCES speakers(id)
        ON DELETE SET NULL,

    INDEX idx_segments_lesson (lesson_id),
    INDEX idx_segments_time (lesson_id, start_time)
);

CREATE TABLE expressions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT UNSIGNED NOT NULL,
    english TEXT NOT NULL,
    portuguese TEXT DEFAULT NULL,
    audio_segment_id INT UNSIGNED DEFAULT NULL,
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_expressions_lesson
        FOREIGN KEY (lesson_id)
        REFERENCES lessons(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_expressions_segment
        FOREIGN KEY (audio_segment_id)
        REFERENCES lesson_segments(id)
        ON DELETE SET NULL
);

CREATE TABLE flashcards (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expression_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(500) DEFAULT NULL,
    mastery_level TINYINT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_flashcards_expression
        FOREIGN KEY (expression_id)
        REFERENCES expressions(id)
        ON DELETE CASCADE
);

CREATE TABLE quiz_questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT UNSIGNED NOT NULL,
    question_type ENUM(
        'multiple_choice',
        'fill_blank',
        'translation',
        'audio'
    ) NOT NULL,
    question TEXT NOT NULL,
    correct_answer TEXT NOT NULL,
    explanation TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_quiz_lesson
        FOREIGN KEY (lesson_id)
        REFERENCES lessons(id)
        ON DELETE CASCADE
);

CREATE TABLE quiz_answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id INT UNSIGNED NOT NULL,
    answer TEXT NOT NULL,
    is_correct BOOLEAN NOT NULL DEFAULT FALSE,

    CONSTRAINT fk_answers_question
        FOREIGN KEY (question_id)
        REFERENCES quiz_questions(id)
        ON DELETE CASCADE
);

CREATE TABLE user_progress (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT UNSIGNED NOT NULL,
    status ENUM(
        'not_started',
        'in_progress',
        'completed',
        'review'
    ) NOT NULL DEFAULT 'not_started',
    progress_percent DECIMAL(5,2) DEFAULT 0,
    last_position_seconds DECIMAL(10,3) DEFAULT 0,
    last_studied_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,

    CONSTRAINT fk_progress_lesson
        FOREIGN KEY (lesson_id)
        REFERENCES lessons(id)
        ON DELETE CASCADE,

    UNIQUE KEY uk_progress_lesson (lesson_id)
);

CREATE TABLE quiz_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT UNSIGNED NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    total_questions INT UNSIGNED NOT NULL,
    correct_answers INT UNSIGNED NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_attempts_lesson
        FOREIGN KEY (lesson_id)
        REFERENCES lessons(id)
        ON DELETE CASCADE
);

CREATE TABLE study_schedule (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT UNSIGNED NOT NULL,
    scheduled_date DATE NOT NULL,
    completed BOOLEAN NOT NULL DEFAULT FALSE,

    CONSTRAINT fk_schedule_lesson
        FOREIGN KEY (lesson_id)
        REFERENCES lessons(id)
        ON DELETE CASCADE,

    INDEX idx_schedule_date (scheduled_date)
);

/*Criar tabelas - FIM*/

/*DADOS INICIAIS - INICIO*/

INSERT INTO courses (name, description)
VALUES (
    'Inglês',
    'Curso de inglês com aulas principais, exercícios de leitura, flashcards e quizzes.'
);

INSERT INTO speakers (code, name)
VALUES
    ('A', 'Speaker A'),
    ('B', 'Speaker B'),
    ('C', 'Speaker C');

INSERT INTO lessons (
    course_id,
    lesson_number,
    title,
    type,
    duration_seconds
) VALUES (
    1,
    1,
    'Lesson 1',
    'main',
    1822
);

UPDATE lessons
SET audio_path = '../storage/audio/Lesson1.mp3'
WHERE id = 1;

ALTER TABLE lessons
ADD COLUMN subtitle_offset DECIMAL(5,2) NOT NULL DEFAULT 0.20
AFTER srt_path;

UPDATE lessons
SET subtitle_offset = 0.20
WHERE id = 1;

UPDATE lessons
SET subtitle_offset = 0.20
WHERE id = 2;

DELIMITER $$

CREATE PROCEDURE inserirAulas()*/
BEGIN
    DECLARE i INT DEFAULT 1;

    WHILE i <= 30 DO
        INSERT INTO lessons (
            course_id,
            lesson_number,
            title,
            type,
            duration_seconds,
            audio_path,
            subtitle_offset
        ) VALUES (
            1,
            i,
            CONCAT('Lesson ', i),
            'main',
            1822,
            CONCAT('../storage/audio/Lesson', i, '.mp3'),
            0.20
        );

        SET i = i + 1;
    END WHILE;
END$$

DELIMITER ;

-- Para executar:
CALL inserirAulas();

-- Se quiser remover a procedure depois de usar:
-- DROP PROCEDURE inserirAulas;

/*DADOS INICIAIS - FIM*/
