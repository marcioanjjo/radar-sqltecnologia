<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PortalController
{
    public function index(Request $request, Response $response): Response
    {
        $pdo = Database::getConnection();

        $sql = "SELECT id, titulo, slug, categoria, conteudo_resumo, imagem_destaque, video_url, cliques_total, criado_em 
                FROM noticias 
                WHERE status = 'publicado' 
                ORDER BY criado_em DESC 
                LIMIT 15";

        $stmt = $pdo->query($sql);
        $noticias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $html = $this->renderizarHome($noticias);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function artigo(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'] ?? '';
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM noticias WHERE slug = :slug AND status = 'publicado' LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $noticia = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$noticia) {
            $response->getBody()->write("<h1>404 - Notícia não encontrada</h1>");
            return $response->withStatus(404);
        }

        // Incrementa cliques/visualizações
        $updateStmt = $pdo->prepare("UPDATE noticias SET cliques_total = cliques_total + 1 WHERE id = :id");
        $updateStmt->execute([':id' => $noticia['id']]);

        // Consulta de afiliados vinculados (usando a coluna real 'ativo = 1')
        $afiliadosStmt = $pdo->prepare("SELECT * FROM links_afiliados WHERE noticia_id = :id AND ativo = 1 LIMIT 4");
        $afiliadosStmt->execute([':id' => $noticia['id']]);
        $afiliados = $afiliadosStmt->fetchAll(PDO::FETCH_ASSOC);

        $html = $this->renderizarArtigo($noticia, $afiliados);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function termos(Request $request, Response $response): Response
    {
        $conteudo = "
        <p><strong>Última atualização: Outubro de 2026</strong></p>

        <h3>1. Aceitação dos Termos</h3>
        <p>Ao acessar e navegar no <strong>Radar Notícias</strong>, o usuário concorda expressamente em cumprir e respeitar os presentes Termos de Uso, bem como todas as leis e regulamentos aplicáveis.</p>

        <h3>2. Propriedade Intelectual e Curadoria Editorial</h3>
        <p>Todo o conteúdo textual, análises editoriais, sínteses técnicas e layouts desenvolvidos pela equipe da <strong>SQL Tecnologia</strong> são protegidos pelas leis de propriedade intelectual e direitos autorais. É proibida a reprodução integral, redistribuição ou exploração comercial não autorizada do material sem a devida citação e atribuição de link de referência ao Radar Notícias.</p>

        <h3>3. Isenção de Responsabilidade sobre Conteúdo e Links Externos</h3>
        <p>O conteúdo publicado no Radar Notícias possui caráter estritamente analítico e informativo sobre inovação, modelos de inteligência artificial e ecossistemas de software. O portal pode conter hiperligações para sites de terceiros, ferramentas ou fontes oficiais. Não exercemos qualquer controle sobre as práticas, políticas de privacidade ou conteúdos desses serviços externos, declinando qualquer responsabilidade por perdas ou danos decorrentes da sua utilização.</p>

        <h3>4. Conduta do Usuário</h3>
        <p>O usuário compromete-se a utilizar a plataforma de forma lícita, não praticando atos que possam comprometer a segurança, disponibilidade ou integridade dos servidores, bancos de dados e redes associadas ao serviço.</p>

        <h3>5. Modificações dos Termos</h3>
        <p>A SQL Tecnologia reserva-se o direito de rever e atualizar estes termos a qualquer momento, sem aviso prévio, entrando as alterações em vigor imediatamente após a sua publicação nesta página.</p>";

        $html = $this->renderizarPaginaInstitucional('Termos de Uso', $conteudo);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function privacidade(Request $request, Response $response): Response
    {
        $conteudo = "
        <p><strong>Última atualização: Outubro de 2026</strong></p>
        <p>A sua privacidade é de extrema importância para nós. A presente Política de Privacidade descreve como o <strong>Radar Notícias</strong> (operado pela SQL Tecnologia) recolhe, utiliza, armazena e protege os dados gerados pela sua navegação no domínio e subdomínios associados.</p>

        <h3>1. Recolha de Informações e Métricas de Navegação</h3>
        <p>O Radar Notícias não recolhe informações de identificação pessoal (PII) dos seus visitantes, salvo quando fornecidas de forma voluntária. Recolhemos dados técnicos através de arquivos de registro (endereço IP anonimizado, tipo de navegador e tempo de permanência) estritamente para métricas estatísticas e estabilidade do serviço.</p>

        <h3>2. Cookies e Web Beacons</h3>
        <p>Utilizamos cookies técnicos essenciais para permitir o funcionamento adequado da plataforma. Adicionalmente, fornecedores terceiros, incluindo o Google, utilizam cookies para veicular anúncios com base nas visitas anteriores dos usuários ao nosso website ou a outros websites na Internet.</p>

        <h3>3. Fornecedores de Publicidade (Google AdSense)</h3>
        <p>O Google é um fornecedor terceiro no nosso portal. A utilização de cookies de publicidade permite ao Google e aos seus parceiros apresentar anúncios aos nossos usuários com base nas suas visitas a este e a outros websites:</p>
        <ul>
            <li>Os usuários podem desativar a publicidade personalizada acessando as <a href='https://www.google.com/settings/ads' target='_blank' rel='noopener noreferrer'>Configurações de Anúncios do Google</a>.</li>
            <li>Em alternativa, pode desativar a utilização de cookies de terceiros para publicidade personalizada acessando o portal <a href='https://www.aboutads.info/choices/' target='_blank' rel='noopener noreferrer'>aboutads.info</a>.</li>
        </ul>

        <h3>4. Conformidade com a LGPD e Direitos do Titular</h3>
        <p>O tratamento de quaisquer métricas ou dados enquadra-se nas diretrizes da Lei Geral de Proteção de Dados (Lei nº 13.709/2018 - LGPD). Nenhum dado sensível é processado ou compartilhado comercialmente com terceiros não autorizados.</p>

        <h3>5. Contato e Dúvidas</h3>
        <p>Para esclarecimentos sobre a nossa política de privacidade ou sobre o tratamento de informações, entre em contato através do e-mail oficial: <strong>contato@sqltecnologia.inf.br</strong>.</p>";

        $html = $this->renderizarPaginaInstitucional('Política de Privacidade', $conteudo);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Converte links comuns do YouTube (watch, youtu.be, shorts) para o formato /embed/
     */
    private function formatarUrlVideo(string $url): string
    {
        $url = trim($url);
        if (empty($url)) {
            return '';
        }

        // Captura o ID do vídeo em formatos youtube.com/watch?v=, youtu.be/, youtube.com/shorts/ ou youtube.com/embed/
        if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1];
        }

        return $url;
    }

    private function renderizarHome(array $noticias): string
    {
        $cards = '';
        foreach ($noticias as $item) {
            $dataCriacao = date('d/m/Y H:i', strtotime($item['criado_em']));
            $imagemTag = '';
            if (!empty($item['imagem_destaque'])) {
                $imagemTag = "<img src='" . htmlspecialchars($item['imagem_destaque']) . "' alt='" . htmlspecialchars($item['titulo']) . "' style='width: 100%; height: 200px; object-fit: cover; border-top-left-radius: 8px; border-top-right-radius: 8px;'>";
            }

            $cards .= "
            <article style='background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; flex-direction: column; overflow: hidden;'>
                {$imagemTag}
                <div style='padding: 20px; flex: 1; display: flex; flex-direction: column;'>
                    <span style='color: #2563eb; font-size: 0.8rem; font-weight: bold; text-transform: uppercase; margin-bottom: 8px; display: inline-block;'>" . htmlspecialchars($item['categoria']) . "</span>
                    <h2 style='margin: 0 0 10px 0; font-size: 1.25rem; line-height: 1.4;'>
                        <a href='/noticia/" . htmlspecialchars($item['slug']) . "' style='color: #111827; text-decoration: none;'>" . htmlspecialchars($item['titulo']) . "</a>
                    </h2>
                    <p style='color: #4b5563; font-size: 0.95rem; line-height: 1.5; margin-bottom: 16px; flex: 1;'>" . htmlspecialchars($item['conteudo_resumo']) . "</p>
                    <div style='display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; color: #6b7280; border-top: 1px solid #f3f4f6; padding-top: 12px;'>
                        <span>{$dataCriacao}</span>
                        <span>" . (int)$item['cliques_total'] . " leituras</span>
                    </div>
                </div>
            </article>";
        }

        if (empty($cards)) {
            $cards = "<div style='grid-column: 1/-1; text-align: center; padding: 40px; color: #6b7280;'>Nenhuma matéria publicada até o momento.</div>";
        }

        return "
        <!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Radar Notícias - Tecnologia e Inovação</title>
            <style>
                body { font-family: system-ui, -apple-system, sans-serif; background: #f8fafc; margin: 0; color: #0f172a; }
                header { background: #0f172a; color: white; padding: 20px 24px; border-bottom: 2px solid #2563eb; }
                .nav-container { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
                .nav-container h1 { margin: 0; font-size: 1.5rem; }
                .nav-container a { color: white; text-decoration: none; font-weight: bold; }
                .grid { max-width: 1200px; margin: 30px auto; padding: 0 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; }
                footer { background: #0f172a; color: #94a3b8; padding: 32px 20px; margin-top: 60px; font-size: 0.9rem; border-top: 1px solid #1e293b; }
                .footer-container { max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; gap: 14px; text-align: center; }
                .footer-links a { color: #cbd5e1; text-decoration: none; margin: 0 8px; }
                .footer-links a:hover { color: #60a5fa; }
            </style>
        </head>
        <body>
            <header>
                <div class='nav-container'>
                    <h1><a href='/'>Radar Notícias</a></h1>
                    <a href='/admin/dashboard' style='font-size: 0.9rem; background: #1e293b; padding: 6px 12px; border-radius: 4px;'>Área do Redator</a>
                </div>
            </header>

            <main class='grid'>
                {$cards}
            </main>

            <footer>
                <div class='footer-container'>
                    <div><strong>Radar Notícias</strong> — Curadoria tecnológica, análises editoriais e inovação.</div>
                    <div class='footer-links'>
                        <a href='/termos'>Termos de Uso</a> • 
                        <a href='/privacidade'>Política de Privacidade</a> • 
                        <a href='https://sqltecnologia.inf.br' target='_blank' rel='noopener noreferrer'>SQL Tecnologia</a>
                    </div>
                    <div>&copy; 2026 <strong>SQLTECNOLOGIA.INF.BR</strong> — Todos os direitos reservados.</div>
                </div>
            </footer>
        </body>
        </html>";
    }

    private function renderizarArtigo(array $noticia, array $afiliados): string
    {
        $dataCriacao = date('d/m/Y H:i', strtotime($noticia['criado_em']));

        // Imagem interna de destaque opcional
        $imagemHeader = '';
        if (!empty($noticia['imagem_destaque'])) {
            $imagemHeader = "<div style='margin-bottom: 24px;'><img src='" . htmlspecialchars($noticia['imagem_destaque']) . "' alt='" . htmlspecialchars($noticia['titulo']) . "' style='width: 100%; max-height: 480px; object-fit: cover; border-radius: 8px;'></div>";
        }

        // Vídeo convertido para embed e posicionado no topo do artigo
        $boxVideo = '';
        if (!empty($noticia['video_url'])) {
            $videoEmbedUrl = $this->formatarUrlVideo($noticia['video_url']);
            $boxVideo = "
            <div style='margin: 24px 0;'>
                <div style='position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 8px; background: #000;'>
                    <iframe src='" . htmlspecialchars($videoEmbedUrl) . "' style='position: absolute; top:0; left: 0; width: 100%; height: 100%; border: 0;' allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture' allowfullscreen></iframe>
                </div>
            </div>";
        }

        // Permite tags HTML formatadas pelo TinyMCE (tabelas, listas, negrito, etc)
        $tagsPermitidas = '<p><br><strong><b><em><i><u><h2><h3><h4><h5><h6><ul><ol><li><table><thead><tbody><tfoot><tr><th><td><blockquote><code><pre><a><hr>';
        $conteudoFormatado = strip_tags($noticia['conteudo_completo'], $tagsPermitidas);

        // Parecer Editorial com suporte total a HTML e Tabelas
        $boxEditorial = '';
        if (!empty($noticia['opiniao_editorial'])) {
            $opiniaoFormatada = strip_tags($noticia['opiniao_editorial'], $tagsPermitidas);
            $boxEditorial = "
            <div style='background: #eff6ff; border-left: 4px solid #3b82f6; padding: 24px; border-radius: 6px; margin: 30px 0;'>
                <strong style='color: #1e40af; display: block; margin-bottom: 12px; font-size: 1.1rem;'>Análise Editorial Própria</strong>
                <div class='artigo-corpo' style='color: #1e3a8a;'>
                    {$opiniaoFormatada}
                </div>
            </div>";
        }

        // Bloco de Afiliados (se houver links vinculados)
        $boxAfiliados = '';
        if (!empty($afiliados)) {
            $itensAfiliados = '';
            foreach ($afiliados as $af) {
                $itensAfiliados .= "
                <a href='" . htmlspecialchars($af['url_destino']) . "' target='_blank' rel='nofollow sponsored noopener' style='display: block; background: #f8fafc; border: 1px solid #cbd5e1; padding: 12px 16px; border-radius: 6px; text-decoration: none; color: #0f172a; font-weight: 600; margin-bottom: 8px;'>
                    &#128279; " . htmlspecialchars($af['titulo']) . " &rarr;
                </a>";
            }
            $boxAfiliados = "
            <div style='margin-top: 32px; padding-top: 20px; border-top: 1px solid #e2e8f0;'>
                <strong style='display: block; margin-bottom: 12px; color: #475569; font-size: 0.95rem;'>Links e Recursos Relacionados:</strong>
                {$itensAfiliados}
            </div>";
        }

        return "
        <!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>" . htmlspecialchars($noticia['titulo']) . " - Radar Notícias</title>
            <style>
                body { font-family: system-ui, -apple-system, sans-serif; background: #f8fafc; margin: 0; color: #0f172a; line-height: 1.7; }
                header { background: #0f172a; color: white; padding: 18px 24px; border-bottom: 2px solid #2563eb; }
                .nav-container { max-width: 900px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
                .nav-container a { color: white; text-decoration: none; font-weight: bold; }
                .container { max-width: 900px; margin: 30px auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
                .meta { color: #64748b; font-size: 0.9rem; margin-bottom: 24px; display: flex; gap: 15px; }
                
                /* Estilos das tabelas dentro do artigo e da análise editorial */
                .artigo-corpo { font-size: 1.05rem; color: #1e293b; }
                .artigo-corpo table { width: 100%; border-collapse: collapse; margin: 24px 0; font-size: 0.95rem; overflow-x: auto; display: block; }
                .artigo-corpo th { background-color: #0f172a; color: #ffffff; padding: 12px 16px; border: 1px solid #cbd5e1; text-align: left; }
                .artigo-corpo td { padding: 12px 16px; border: 1px solid #cbd5e1; color: #334155; }
                .artigo-corpo tr:nth-child(even) { background-color: #f8fafc; }
                .artigo-corpo ul, .artigo-corpo ol { padding-left: 24px; margin: 16px 0; }
                .artigo-corpo li { margin-bottom: 8px; }
                
                footer { background: #0f172a; color: #94a3b8; padding: 32px 20px; margin-top: 60px; font-size: 0.9rem; text-align: center; }
                .footer-links a { color: #cbd5e1; text-decoration: none; margin: 0 8px; }
            </style>
        </head>
        <body>
            <header>
                <div class='nav-container'>
                    <a href='/' style='font-size: 1.4rem;'>Radar Notícias</a>
                    <a href='/' style='font-size: 0.9rem; color: #94a3b8;'>&larr; Voltar ao Portal</a>
                </div>
            </header>

            <main class='container'>
                <span style='color: #2563eb; font-weight: bold; text-transform: uppercase; font-size: 0.85rem;'>" . htmlspecialchars($noticia['categoria']) . "</span>
                <h1 style='margin: 10px 0 16px 0; font-size: 2.2rem; line-height: 1.3;'>" . htmlspecialchars($noticia['titulo']) . "</h1>
                
                <div class='meta'>
                    <span>Publicado em: {$dataCriacao}</span>
                    <span>•</span>
                    <span>Visualizações: " . (int)$noticia['cliques_total'] . "</span>
                </div>

                {$imagemHeader}
                {$boxVideo}

                <div class='artigo-corpo'>
                    {$conteudoFormatado}
                </div>

                {$boxEditorial}
                {$boxAfiliados}
            </main>

            <footer>
                <div style='max-width: 900px; margin: 0 auto; display: flex; flex-direction: column; gap: 12px;'>
                    <div class='footer-links'>
                        <a href='/termos'>Termos de Uso</a> • 
                        <a href='/privacidade'>Política de Privacidade</a> • 
                        <a href='https://sqltecnologia.inf.br' target='_blank' rel='noopener noreferrer'>SQL Tecnologia</a>
                    </div>
                    <div>&copy; 2026 <strong>SQLTECNOLOGIA.INF.BR</strong> — Todos os direitos reservados.</div>
                </div>
            </footer>
        </body>
        </html>";
    }

    private function renderizarPaginaInstitucional(string $titulo, string $corpoHtml): string
    {
        return "
        <!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$titulo} - Radar Notícias</title>
            <style>
                body { font-family: system-ui, -apple-system, sans-serif; background: #0f172a; margin: 0; color: #334155; }
                header { background: #0f172a; color: white; padding: 20px 24px; border-bottom: 2px solid #2563eb; }
                .nav-container { max-width: 960px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
                .nav-container a { color: white; text-decoration: none; font-weight: bold; }
                .container { max-width: 960px; margin: 40px auto; background: white; padding: 36px 40px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); line-height: 1.7; }
                h2 { color: #0f172a; margin-top: 0; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; }
                h3 { color: #1e293b; margin-top: 24px; }
                a { color: #2563eb; }
                footer { background: #0f172a; color: #94a3b8; padding: 32px 20px; text-align: center; font-size: 0.9rem; border-top: 1px solid #1e293b; }
                .footer-links a { color: #cbd5e1; text-decoration: none; margin: 0 8px; }
            </style>
        </head>
        <body>
            <header>
                <div class='nav-container'>
                    <a href='/' style='font-size: 1.4rem;'>Radar Notícias</a>
                    <a href='/' style='font-size: 0.9rem; color: #94a3b8;'>&larr; Início</a>
                </div>
            </header>

            <main class='container'>
                <h2>{$titulo}</h2>
                {$corpoHtml}
            </main>

            <footer>
                <div style='max-width: 960px; margin: 0 auto; display: flex; flex-direction: column; gap: 12px;'>
                    <div class='footer-links'>
                        <a href='/termos'>Termos de Uso</a> • 
                        <a href='/privacidade'>Política de Privacidade</a> • 
                        <a href='https://sqltecnologia.inf.br' target='_blank' rel='noopener noreferrer'>SQL Tecnologia</a>
                    </div>
                    <div>&copy; 2026 <strong>SQLTECNOLOGIA.INF.BR</strong> — Todos os direitos reservados.</div>
                </div>
            </footer>
        </body>
        </html>";
    }
}
