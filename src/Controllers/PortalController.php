<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PortalController
{
    // Ecrã Inicial / Feed de Notícias Públicas
    public function index(Request $request, Response $response): Response
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT id, titulo, slug, categoria, conteudo_resumo, imagem_destaque, criado_em, cliques_total 
            FROM noticias 
            WHERE status = 'publicado' 
            ORDER BY criado_em DESC 
            LIMIT 20
        ");
        $stmt->execute();
        $noticias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $html = $this->renderizarCapa($noticias);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    // Leitura Completa da Matéria por Slug
    public function artigo(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'] ?? '';
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT * 
            FROM noticias 
            WHERE slug = :slug AND status = 'publicado' 
            LIMIT 1
        ");
        $stmt->execute([':slug' => $slug]);
        $noticia = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$noticia) {
            $response->getBody()->write("<h1>404 - Notícia não encontrada</h1>");
            return $response->withStatus(404)->withHeader('Content-Type', 'text/html; charset=UTF-8');
        }

        // Registo e contabilização da leitura/clique
        $this->registarLeitura($pdo, (int)$noticia['id'], $request);

        $html = $this->renderizarArtigo($noticia);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    // Página institucional: Termos de Uso
    public function termos(Request $request, Response $response): Response
    {
        $html = $this->renderizarPaginaInstitucional(
            'Termos de Uso',
            'O conteúdo publicado no Radar Notícias possui caráter exclusivamente informativo e analítico. Todo o material autoral é protegido por direitos autorais sob a chancela da SQL Tecnologia.'
        );
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    // Página institucional: Política de Privacidade (Essencial para Google AdSense)
    public function privacidade(Request $request, Response $response): Response
    {
        $html = $this->renderizarPaginaInstitucional(
            'Política de Privacidade',
            'O Radar Notícias respeita a privacidade de seus utilizadores. Utilizamos identificadores e cookies técnicos para análise de métricas anonimizadas e veiculação de anúncios através do Google AdSense. Em conformidade com a LGPD, nenhum dado sensível ou identificável é armazenado sem consentimento.'
        );
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    private function registarLeitura(PDO $pdo, int $noticiaId, Request $request): void
    {
        try {
            // Incrementa o contador total da matéria
            $stmtUpdate = $pdo->prepare("UPDATE noticias SET cliques_total = cliques_total + 1 WHERE id = :id");
            $stmtUpdate->execute([':id' => $noticiaId]);

            // Registo analítico anonimizado para métricas
            $serverParams = $request->getServerParams();
            $ip = $serverParams['REMOTE_ADDR'] ?? '0.0.0.0';
            $ipHash = hash('sha256', $ip);
            $userAgent = substr($serverParams['HTTP_USER_AGENT'] ?? '', 0, 255);

            $stmtLog = $pdo->prepare("
                INSERT INTO logs_cliques (noticia_id, tipo_origem, ip_hash, user_agent, clicado_em) 
                VALUES (:id, 'noticia_leitura', :ip_hash, :user_agent, NOW())
            ");
            $stmtLog->execute([
                ':id'         => $noticiaId,
                ':ip_hash'    => $ipHash,
                ':user_agent' => $userAgent
            ]);
        } catch (\Throwable $e) {
            // Falhas secundárias de telemetria não devem interromper o leitor
        }
    }

    private function renderizarCapa(array $noticias): string
    {
        $cards = '';
        foreach ($noticias as $item) {
            $dataPublicacao = date('d/m/Y H:i', strtotime($item['criado_em']));
            $cards .= "
            <article style='background: white; border-radius: 8px; border: 1px solid #e5e7eb; overflow: hidden; display: flex; flex-direction: column; transition: box-shadow 0.2s;'>
                <div style='padding: 20px; flex-grow: 1; display: flex; flex-direction: column;'>
                    <span style='color: #2563eb; font-weight: 700; font-size: 0.8rem; text-transform: uppercase; margin-bottom: 8px;'>" . htmlspecialchars($item['categoria']) . "</span>
                    <h2 style='font-size: 1.25rem; line-height: 1.4; margin: 0 0 10px 0;'>
                        <a href='/noticia/" . urlencode($item['slug']) . "' style='color: #111827; text-decoration: none;'>" . htmlspecialchars($item['titulo']) . "</a>
                    </h2>
                    <p style='color: #4b5563; font-size: 0.95rem; line-height: 1.5; margin: 0 0 16px 0; flex-grow: 1;'>" . htmlspecialchars($item['conteudo_resumo']) . "</p>
                    <div style='display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f3f4f6; padding-top: 12px; font-size: 0.8rem; color: #6b7280;'>
                        <span>{$dataPublicacao}</span>
                        <span>" . (int)$item['cliques_total'] . " leituras</span>
                    </div>
                </div>
            </article>";
        }

        if (empty($cards)) {
            $cards = "<p style='text-align: center; color: #6b7280; grid-column: 1 / -1; padding: 40px;'>Nenhuma notícia publicada até ao momento.</p>";
        }

        $footer = $this->renderizarFooter();

        return "
        <!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Radar Notícias - Tecnologia e Inovação</title>
            <style>
                body { font-family: system-ui, -apple-system, sans-serif; background: #f9fafb; margin: 0; color: #111827; display: flex; flex-direction: column; min-height: 100vh; }
                header { background: #1e293b; color: white; padding: 1.25rem 2rem; border-bottom: 3px solid #2563eb; }
                .nav-container { max-width: 1100px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
                .grid { max-width: 1100px; margin: 40px auto; padding: 0 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; flex-grow: 1; width: 100%; box-sizing: border-box; }
            </style>
        </head>
        <body>
            <header>
                <div class='nav-container'>
                    <h1 style='margin: 0; font-size: 1.5rem;'><a href='/' style='color: white; text-decoration: none;'>Radar Notícias</a></h1>
                    <a href='/admin/dashboard' style='color: #cbd5e1; text-decoration: none; font-size: 0.9rem;'>Área do Redator</a>
                </div>
            </header>
            <main class='grid'>
                {$cards}
            </main>
            {$footer}
        </body>
        </html>";
    }

    private function renderizarArtigo(array $noticia): string
    {
        $dataPublicacao = date('d/m/Y \à\s H:i', strtotime($noticia['criado_em']));

        $seccaoEditorial = '';
        if (!empty($noticia['opiniao_editorial'])) {
            $seccaoEditorial = "
            <aside style='background: #eff6ff; border-left: 4px solid #2563eb; padding: 16px 20px; border-radius: 4px; margin: 2rem 0;'>
                <h4 style='color: #1e40af; margin-top: 0; margin-bottom: 8px;'>Análise do Radar</h4>
                <p style='color: #1e3a8a; line-height: 1.6; margin: 0; font-size: 0.95rem;'>" . nl2br(htmlspecialchars($noticia['opiniao_editorial'])) . "</p>
            </aside>";
        }

        $seccaoFonte = '';
        if (!empty($noticia['url_fonte'])) {
            $seccaoFonte = "
            <p style='margin-top: 2rem; font-size: 0.85rem; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 16px;'>
                Fonte primária consultada: <a href='" . htmlspecialchars($noticia['url_fonte']) . "' target='_blank' rel='noopener noreferrer' style='color: #2563eb;'>" . htmlspecialchars($noticia['url_fonte']) . "</a>
            </p>";
        }

        $footer = $this->renderizarFooter();

        return "
        <!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>" . htmlspecialchars($noticia['titulo']) . " - Radar Notícias</title>
            <meta name='description' content='" . htmlspecialchars($noticia['conteudo_resumo']) . "'>
            <style>
                body { font-family: system-ui, -apple-system, sans-serif; background: #f9fafb; margin: 0; color: #111827; display: flex; flex-direction: column; min-height: 100vh; }
                header { background: #1e293b; color: white; padding: 1.25rem 2rem; border-bottom: 3px solid #2563eb; }
                .nav-container { max-width: 800px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
                .content-container { max-width: 800px; width: 100%; margin: 40px auto; padding: 32px 24px; background: white; border-radius: 8px; border: 1px solid #e5e7eb; box-sizing: border-box; flex-grow: 1; }
                .article-body { font-size: 1.1rem; line-height: 1.8; color: #374151; }
            </style>
        </head>
        <body>
            <header>
                <div class='nav-container'>
                    <h1 style='margin: 0; font-size: 1.5rem;'><a href='/' style='color: white; text-decoration: none;'>Radar Notícias</a></h1>
                    <a href='/' style='color: #cbd5e1; text-decoration: none; font-size: 0.9rem;'>← Início</a>
                </div>
            </header>
            <main class='content-container'>
                <span style='color: #2563eb; font-weight: 700; font-size: 0.85rem; text-transform: uppercase;'>" . htmlspecialchars($noticia['categoria']) . "</span>
                <h1 style='font-size: 2.1rem; line-height: 1.3; margin: 12px 0 16px 0;'>" . htmlspecialchars($noticia['titulo']) . "</h1>
                <p style='color: #6b7280; font-size: 0.9rem; margin-bottom: 24px;'>Publicado em {$dataPublicacao} • " . (int)$noticia['cliques_total'] . " leituras</p>
                
                <p style='font-size: 1.15rem; color: #4b5563; font-style: italic; border-left: 3px solid #9ca3af; padding-left: 14px; margin-bottom: 30px;'>" . htmlspecialchars($noticia['conteudo_resumo']) . "</p>

                <div class='article-body'>
                    " . nl2br(htmlspecialchars($noticia['conteudo_completo'])) . "
                </div>

                {$seccaoEditorial}
                {$seccaoFonte}
            </main>
            {$footer}
        </body>
        </html>";
    }

    private function renderizarFooter(): string
    {
        $anoAtual = date('Y');
        return "
        <footer style='background: #0f172a; color: #94a3b8; padding: 40px 20px; margin-top: 60px; border-top: 1px solid #334155; font-size: 0.9rem;'>
            <div style='max-width: 1100px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 16px;'>
                <div>
                    <strong style='color: white; font-size: 1.1rem;'>Radar Notícias</strong>
                    <p style='margin: 6px 0 0 0; color: #64748b;'>Curadoria tecnológica, análises editoriais e inovação.</p>
                </div>

                <div style='display: flex; gap: 20px; font-size: 0.85rem;'>
                    <a href='/termos' style='color: #cbd5e1; text-decoration: none;'>Termos de Uso</a>
                    <span style='color: #475569;'>•</span>
                    <a href='/privacidade' style='color: #cbd5e1; text-decoration: none;'>Política de Privacidade</a>
                    <span style='color: #475569;'>•</span>
                    <a href='https://sqltecnologia.inf.br' target='_blank' rel='noopener noreferrer' style='color: #38bdf8; text-decoration: none; font-weight: 500;'>SQL Tecnologia</a>
                </div>

                <div style='border-top: 1px solid #1e293b; width: 100%; max-width: 600px; padding-top: 16px; font-size: 0.8rem; color: #64748b;'>
                    © {$anoAtual} <strong>SQLTECNOLOGIA.INF.BR</strong> — Todos os direitos reservados.
                </div>
            </div>
        </footer>";
    }

    private function renderizarPaginaInstitucional(string $titulo, string $texto): string
    {
        $footer = $this->renderizarFooter();
        return "
        <!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$titulo} - Radar Notícias</title>
            <style>
                body { font-family: system-ui, -apple-system, sans-serif; background: #f9fafb; margin: 0; color: #111827; display: flex; flex-direction: column; min-height: 100vh; }
                header { background: #1e293b; color: white; padding: 1.25rem 2rem; border-bottom: 3px solid #2563eb; }
                .nav-container { max-width: 800px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
                .content { max-width: 800px; width: 100%; margin: 40px auto; padding: 32px 24px; background: white; border-radius: 8px; border: 1px solid #e5e7eb; box-sizing: border-box; flex-grow: 1; }
            </style>
        </head>
        <body>
            <header>
                <div class='nav-container'>
                    <h1 style='margin: 0; font-size: 1.5rem;'><a href='/' style='color: white; text-decoration: none;'>Radar Notícias</a></h1>
                    <a href='/' style='color: #cbd5e1; text-decoration: none; font-size: 0.9rem;'>← Início</a>
                </div>
            </header>
            <main class='content'>
                <h1 style='margin-top: 0;'>{$titulo}</h1>
                <p style='line-height: 1.7; color: #374151; font-size: 1.05rem;'>{$texto}</p>
            </main>
            {$footer}
        </body>
        </html>";
    }
}
