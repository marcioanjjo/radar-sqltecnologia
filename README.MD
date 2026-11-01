📡 Radar Notícias — Automated Content Pipeline & Tech Editorial Portal

Plataforma completa de curadoria, reescrita inteligente e publicação automatizada de notícias de tecnologia e IA, alimentada pela API Google Gemini, Slim Framework 4, PHP 8 e arquitetura orientada a SEO e AdSense Compliance.

🎯 Sobre o Projeto

O Radar Notícias é uma solução de ponta a ponta desenvolvida para solucionar o desafio de produzir e atualizar portais editoriais de nicho de forma consistente, escalável e com alto valor agregado.

Em vez de simples cópia de feeds RSS, o sistema implementa um pipeline de processamento de linguagem natural (NLP) via API do Google Gemini. A inteligência artificial consome fontes de alta relevância, sintetiza fatos, extrai entidades-chave, contextualiza impactos mercadológicos e formata o conteúdo em padrões rigorosos de legibilidade, SEO e diretrizes de conformidade editorial (Google AdSense).

🚀 Principais Funcionalidades

🤖 Pipeline Automatizado (Cron & Gemini AI)

Coleta periódica de temas e tendências no ecossistema de Tecnologia, IA e Software.

Processamento e estruturação automática com resumos de capa, corpo estruturado e slug amigável.

Criação e classificação de rascunhos para curadoria editorial antes da publicação ao público geral.

✍️ Painel Administrativo Editorial (Redação CMS)

Dashboard com métricas de cliques, contagem de matérias e filtros por estado (rascunho, publicado, arquivado).

Suporte nativo a envio de imagens locais com sanitização, geração de hashes únicos e armazenamento em diretórios públicos.

Suporte a links de vídeos dinâmicos (YouTube ou MP4 direto).

Módulo de Análise Editorial Própria, permitindo adicionar pareceres técnicos autorais diretamente aos artigos para enriquecer o valor agregado (EEAT do Google).

🌐 Portal Público de Leitura

Design responsivo focado em tempo de leitura, hierarquia tipográfica e performance (Core Web Vitals).

Estrutura pronta para blocos de publicidade programática (AdSense, Banners e Links de Afiliados contextuais).

Roteamento limpo com slugs amigáveis e metadados OpenGraph para compartilhamento social.

🏗️ Arquitetura e Tecnologias

Backend: PHP 8.2+ utilizando arquitetura PSR-7 e PSR-15 com Slim Framework 4.

Banco de Dados: MySQL 8 / MariaDB com views relacionais e persistência otimizada via PDO.

Integração de IA: API Google Gemini (via cURL/HTTP Client customizado) com tratamento de respostas estruturadas.

Ambientes:

Local: Docker + Docker Compose (Nginx, PHP-FPM, MySQL).

Produção: Servidor Linux (HostGator), com segregação segura entre diretório raiz de código (radar_backend) e diretório estático público (public_html / subdomínio com SSL).

### 📁 Estrutura de Pastas

```text
├── radar_backend/
│   ├── bin/
│   │   └── cron_gemini.php          # Rotina de automação disparada via Cron
│   ├── src/
│   │   ├── Config/
│   │   │   └── Database.php         # Conexão singleton PDO com MySQL
│   │   ├── Controllers/
│   │   │   ├── NoticiaAdminController.php # CMS administrativo, upload e edição
│   │   │   └── PortalController.php       # Renderização pública do portal e rotas
│   │   ├── Middlewares/
│   │   │   └── AdminAuthMiddleware.php    # Proteção de rotas da redação
│   │   └── Services/
│   │       └── GeminiCuratorService.php   # Chamadas ao Gemini e estruturação
│   ├── composer.json
│   └── .env.example
├── radar.sqltecnologia.inf.br/      # Raiz pública (Web Root no HostGator)
│   ├── assets/                      # Arquivos CSS, JavaScript e fontes
│   ├── uploads/                     # Mídias locais enviadas pelo painel
│   ├── .htaccess                    # Regras mod_rewrite para o Slim
│   ├── ads.txt                      # Validação oficial do Google AdSense
│   └── index.php                    # Front Controller da aplicação
└── README.md
```
⚙️ Configuração e Instalação

Pré-requisitos

PHP 8.2 ou superior com extensões pdo_mysql, mbstring, curl.

Composer instalado.

Chave de API ativa do Google AI Studio (Gemini API).

Passo a Passo

1. Clonar o repositório:

git clone https://github.com/seu-usuario/radar-noticias.git
cd radar-noticias


2. Instalar dependências:

cd radar_backend
composer install --no-dev --optimize-autoloader


3. Configurar variáveis de ambiente:
Copie o modelo e preencha suas credenciais:

cp .env.example .env


Exemplo de configuração:

APP_ENV=production
APP_DEBUG=false

DB_HOST=localhost
DB_NAME=radar_db
DB_USER=seu_usuario
DB_PASS=sua_senha

GEMINI_API_KEY=sua_chave_aqui


4. Importar banco de dados:
Execute a estrutura de tabelas (noticias, categorias, afiliados, usuarios_admin) e a view de compatibilidade links_afiliados.

5. Configurar permissões de upload:
Certifique-se de que a pasta uploads do subdomínio público tenha permissão de escrita (0755).

6. Configurar Cron de Automação (cPanel / Linux):

0 */4 * * * /usr/bin/php /caminho/para/radar_backend/bin/cron_gemini.php >> /caminho/para/radar_backend/cron.log 2>&1


🛡️ Licença

Licença Distribuído sob a licença MIT. Consulte LICENSE para mais detalhes.

Desenvolvido por Marcio dos Anjos Silva —  [Portfólio](https://sqltecnologia.inf.br) | [GitHub](https://github.com/anjos1867)
