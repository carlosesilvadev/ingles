# Curso de Inglês — versão estática (HTML + CSS + JS)

Este é o mesmo site do repositório `ingles`, mas sem PHP e sem banco de dados.
Todos os dados (título, número da aula, legendas com tempo/texto) foram
extraídos diretamente dos arquivos `.srt` que já existem em `storage/srt`
no repositório original.

## Arquivos gerados aqui
- `index.html` — lista das 30 aulas
- `lesson-1.html` até `lesson-30.html` — player de cada aula, com as
  legendas já embutidas no HTML (sem fetch/API, sem consulta a banco)

## Como publicar (passo a passo)

1. No seu repositório `ingles` (localmente ou direto pelo GitHub),
   copie estes arquivos (`index.html`, `lesson-1.html` ... `lesson-30.html`)
   para a **raiz do repositório**, ao lado da pasta `storage/` que já existe
   (ela contém `storage/audio/LessonN.mp3`, que os players referenciam
   diretamente).

2. Pode remover (ou apenas ignorar/deixar de lado) os arquivos PHP —
   `public/index.php`, `public/player_lesson.php`, `public/process_lesson.php`,
   `public/import_lesson.php` e `config/database.php` — eles não são mais
   necessários para o site público. Guarde-os à parte se quiser continuar
   usando `process_lesson.php` localmente (com PHP + MySQL) para gerar
   novos SRTs no futuro.

3. Suba as mudanças para o GitHub:
   ```bash
   git add index.html lesson-*.html
   git commit -m "Adiciona versão estática do site"
   git push
   ```

4. Ative o GitHub Pages:
   - Vá em **Settings > Pages** no repositório.
   - Em "Source", selecione a branch `main` e a pasta `/ (root)`.
   - Salve. Em alguns minutos o site estará em:
     `https://carlosesilvadev.github.io/ingles/`

## Por que GitHub Pages (e não Netlify/Cloudflare Pages)?
- Os áudios têm ~32MB cada (30 arquivos, quase 1GB no total).
- Cloudflare Pages tem limite de 25MB por arquivo — os MP3s não subiriam.
- Netlify mudou para um sistema de créditos em 2026, mais restritivo
  para servir bastante bandwidth de áudio.
- GitHub Pages tem cap de 100GB/mês de banda (soft limit) e recomendação
  de até 1GB por repositório — seu projeto está dentro desse limite.

## Observações
- O campo `speaker_id` sempre foi `NULL` no banco original, então o
  player estático mostra apenas o intervalo de tempo em vez do nome
  do speaker (igual ao comportamento atual).
- O offset de sincronização de legenda (`subtitle_offset`) foi fixado
  em `0.20`, o mesmo valor usado nos INSERTs do banco.
