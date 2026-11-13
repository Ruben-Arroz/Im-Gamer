# I'm Gamer

**Link para o projeto em direto:** [Aceder ao I'm Gamer](https://esan-tesp-ds-paw.web.ua.pt/tesp-ds-g17/)

## Sobre o Projeto
O **I'm Gamer** é uma plataforma agregadora de estatísticas de videojogos, catálogo de promoções e rede social com sistema de LFG (Looking For Group), para encontrar jogadores e completar equipas.

Este projeto foi desenvolvido no âmbito da unidade curricular de Projeto de Desenvolvimento de Software do TeSP em Desenvolvimento de Software da ESAN (Universidade de Aveiro), 2026/2027.

## Funcionalidades Principais
* **Perfis Unificados:** ligação das contas Steam, Xbox, GOG, Epic Games e PlayStation, com biblioteca, horas jogadas, conquistas e valor estimado de cada conta. As contas Nintendo e Battle.net também se podem ligar, com os jogos adicionados à mão.
* **Estatísticas do jogador:** géneros mais jogados, horas por plataforma, conquistas, jogos nunca abertos e mais.
* **Páginas de jogo:** dados do jogo, preços nas lojas de PC, avaliações dos utilizadores e quem tem o jogo.
* **Lista de desejos:** com o preço mais barato de cada jogo.
* **Catálogo e lançamentos:** promoções do mercado PC e jogos que vão sair, com filtros. Quando a RAWG não responde, aparece a última lista de lançamentos da Steam guardada no site.
* **LFG (Looking For Group):** anúncios para completar equipas, com vagas, entrada e saída da equipa e prazo para ficarem no ar.
* **Convites:** pesquisa de jogadores e convites diretos para jogar, com avisos.
* **Painel de administração:** gestão de utilizadores, anúncios, avaliações e jogos (incluindo juntar jogos repetidos).
* **App Android (em desenvolvimento):** login, lista dos anúncios de LFG e detalhe de cada anúncio, em português e inglês.

## Tecnologias Utilizadas
* **Backend:** PHP 8 puro (sem frameworks)
* **Frontend:** HTML5, CSS3, Vanilla JavaScript
* **Base de Dados:** MySQL (MariaDB)
* **App Android:** Kotlin, com layouts em XML
* **APIs Integradas:** Steam Web API e loja Steam, RAWG, CheapShark, OpenXBL e catálogo da Microsoft (Xbox), GOG, Epic Games e PlayStation (APIs não oficiais das apps) e Frankfurter (taxas de câmbio)

## Instalação e Configuração
*Nota: Sendo um projeto académico alojado em servidores institucionais, o ficheiro com as credenciais da base de dados e das chaves das APIs encontra-se oculto por motivos de segurança.* O ficheiro `projeto/config/credentials.example.php` mostra o que é preciso preencher.

O script da base de dados está em `docs/bd/im_gamer_ddl.sql`.

A app Android está na pasta `android/` e abre-se no Android Studio.
