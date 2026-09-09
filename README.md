# Curso de Inglês Com Legenda Sincronizada

> Aprenda inglês ouvindo, repetindo e lendo ao mesmo tempo — no seu ritmo, quando e onde quiser.

🔗 **Acesse agora:** [carlosesilvadev.github.io/ingles](https://carlosesilvadev.github.io/ingles)

---

## 💡 Por que isso importa

Aprender um idioma não é decorar regras gramaticais — é **treinar o ouvido** e a
**língua** até a fala virar automática. É exatamente assim que uma criança
aprende a falar: ouvindo, repetindo, errando, ouvindo de novo.

Este projeto pega as 30 lições em áudio e coloca a legenda em português/inglês
sincronizada, palavra por palavra, com o que
está tocando. Você:

- 🎧 **Ouve** um diálogo real em inglês
- 👀 **Acompanha** exatamente a frase que está sendo dita, em tempo real
- 🔁 **Clica** em qualquer trecho para repetir, sem precisar ficar arrastando a barra do áudio
- 📈 **Avança** aula por aula, sabendo sempre onde parou

Nada de instalar app, criar conta ou pagar assinatura. Abre o link e começa.

---

## 🚀 Como estudar (em 3 passos)

1. Abra o [site](https://carlosesilvadev.github.io/ingles)
2. Escolha a **Lesson 1** (comece do início — cada aula constrói em cima da anterior)
3. Ouça o áudio inteiro pelo menos uma vez sem se preocupar em entender tudo;
   depois volte e use a legenda para revisar as partes que você não pegou

**Dica:** 25–30 minutos por dia (uma aula por dia) rende muito mais do que estudar
3 horas de vez em quando. Consistência > intensidade.

---

## 🖥️ Como funciona por baixo dos panos

| Recurso | Descrição |
|---|---|
| **30 aulas de áudio** | Trilhas de ~30 minutos cada |
| **Legenda sincronizada** | Cada frase aparece destacada exatamente no segundo certo |
| **Clique para navegar** | Clicar numa legenda pula o áudio direto para aquele trecho |
| **100% estático** | Sem backend, sem banco de dados — carrega rápido em qualquer lugar |

O site é gerado a partir de arquivos `.srt` (legenda com timestamps),
processados uma vez e transformados em páginas HTML autossuficientes.
Você pode rodar tudo localmente sem instalar nada além de um navegador.

---

## 📂 Estrutura do projeto

```
├── index.html            # Lista das 30 aulas
|
└── aulas/
    ├── lesson-1.html          
    ├── lesson-2.html     # Player + legenda sincronizada de cada aula
    ├── ...
    ├── lesson-30.html
└── storage/
    ├── audio/            # Arquivos .mp3 de cada aula
    ├── srt/              # Legendas originais (fonte da verdade)
    └── txt/              # Transcrições em texto puro
```

---

## 🎯 Para quem é este projeto

- Quem já tentou estudar inglês sozinho e travou na parte de "por onde começar"
- Quem prefere aprender ouvindo a decorar listas de vocabulário
- Quem tem pouco tempo por dia, mas quer constância
- Quem gosta de acompanhar o próprio progresso, aula por aula

---

## 🤝 Contribuindo

Sugestões, correções de legenda ou melhorias no player são bem-vindas.
Abra uma *issue* ou um *pull request*.

---

<p align="center">
  <b>A única lição que você não aprende é a que você não começa.</b><br>
  Bons estudos! 🚀
</p>