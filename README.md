# I'm Gamer 🎮

**Link para o projeto em direto:** [Aceder ao I'm Gamer](https://esan-tesp-ds-paw.web.ua.pt/tesp-ds-g17/)

## Sobre o Projeto
O **I'm Gamer** é uma plataforma agregadora de estatísticas de videojogos, catálogo de promoções e rede social com sistema de Matchmaking LFG (Looking For Group). 

Este projeto foi desenvolvido no âmbito da unidade curricular de Programação em Ambiente Web do TeSP em Desenvolvimento de Software da ESAN (Universidade de Aveiro).

## Funcionalidades Principais
* **Perfis Unificados:** ligação das contas Steam, Xbox, GOG e Epic Games, com biblioteca, horas jogadas, conquistas e valor estimado de cada conta.
* **Estatísticas do jogador:** géneros mais jogados, horas por plataforma, conquistas, jogos nunca abertos e mais.
* **Páginas de jogo:** dados do jogo, preços nas lojas de PC, avaliações dos utilizadores e quem tem o jogo.
* **Lista de desejos:** com o preço mais barato de cada jogo.
* **Catálogo e lançamentos:** promoções do mercado PC e jogos que vão sair, com filtros.
* **LFG (Looking For Group):** publicações para encontrar parceiros de equipa (em desenvolvimento).

## Tecnologias Utilizadas
* **Backend:** PHP 8 puro (sem frameworks)
* **Frontend:** HTML5, CSS3, Vanilla JavaScript
* **Base de Dados:** MySQL (MariaDB)
* **APIs Integradas:** Steam Web API, RAWG, CheapShark, OpenXBL e catálogo da Microsoft (Xbox), GOG, Epic Games (API não oficial do launcher) e Frankfurter (taxas de câmbio)

## Instalação e Configuração
*Nota: Sendo um projeto académico alojado em servidores institucionais, o ficheiro com as credenciais da base de dados e das chaves das APIs encontra-se oculto por motivos de segurança.* O ficheiro `projeto/config/credentials.example.php` mostra o que é preciso preencher.

O script da base de dados está em `docs/bd/im_gamer_ddl.sql`.
