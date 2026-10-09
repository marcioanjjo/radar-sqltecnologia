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

        // Procura divulgações ou anúncios especificamente vinculados a esta matéria
        $stmtLinks = $pdo->prepare("
            SELECT id, titulo 
            FROM links_afiliados 
            WHERE noticia_id = :noticia_id AND ativo = 1
        ");
        $stmtLinks->execute([':noticia_id' => $noticia['id']]);
        $linksAfiliados = $stmtLinks->fetchAll(PDO::FETCH_ASSOC);

        $this->registarLeitura($pdo, (int)$noticia['id'], $request);

        $html = $this->renderizarArtigo($noticia, $linksAfiliados);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function termos(Request $request, Response $response): Response
    {
        $html = $this->renderizarPaginaInstitucional(
            'Termos de Uso',
            'O conteúdo publicado no Radar Notícias possui caráter exclusivamente informativo e analítico. Todo o material autoral é protegido por direitos autorais sob a chancela da SQL Tecnologia.'
        );
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

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
            $stmtUpdate = $pdo->prepare("UPDATE noticias SET cliques_total = cliques_total + 1 WHERE id = :id");
            $stmtUpdate->execute([':id' => $noticiaId]);

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
        }
    }

    private function renderizarBlocoPublicidade(string $tipo = 'horizontal'): string
    {
        $estiloDimensoes = $tipo === 'horizontal'
            ? 'min-height: 90px; width: 100%; max-width: 728px;'
            : 'min-height: 250px; width: 100%; max-width: 300px;';

        return "
        <div style='margin: 2rem auto; text-align: center;'>
            <span style='display: block; font-size: 0.7rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;'>Publicidade</span>
            <div style='background: #f1f5f9; border: 1px dashed #cbd5e1; border-radius: 6px; margin: 0 auto; display: flex; align-items: center; justify-content: center; color: #64748b; font-size: 0.85rem; font-weight: 500; {$estiloDimensoes}'>
                <!-- Espaço Reservado para o Google AdSense / Bloco {$tipo} -->
                Espaço Publicitário (AdSense / Banner Parceiro)
            </div>
        </div>";
    }

    private function renderizarPlayerVideo(?string $videoUrl): string
    {
        if (empty($videoUrl)) {
            return '';
        }

        $youtubePattern = '%^(?:https?://)?(?:www\.)?(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|v/|shorts/))([\w-]{11})%x';
        if (preg_match($youtubePattern, $videoUrl, $matches)) {
            $videoId = $matches[1];
            return "
            <div style='position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; border-radius: 8px; margin: 2rem 0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);'>
                <iframe 
                    src='https://www.youtube.com/embed/{$videoId}' 
                    style='position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;' 
                    allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture' 
                    allowfullscreen>
                </iframe>
            </div>";
        }

        return "
        <div style='margin: 2rem 0;'>
            <video controls style='width: 100%; border-radius: 8px; max-height: 480px; background: #000; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);'>
                <source src='" . htmlspecialchars($videoUrl) . "'>
                Seu navegador não suporta o formato deste vídeo.
            </video>
        </div>";
    }

    private function renderizarCapa(array $noticias): string
    {
        $cards = '';
        foreach ($noticias as $item) {
            $dataPublicacao = date('d/m/Y H:i', strtotime($item['criado_em']));

            $tagImagemCapa = '';
            if (!empty($item['imagem_destaque'])) {
                $tagImagemCapa = "<img src='" . htmlspecialchars($item['imagem_destaque']) . "' alt='" . htmlspecialchars($item['titulo']) . "' style='width: 100%; height: 180px; object-fit: cover;'>";
            }

            $cards .= "
            <article style='background: white; border-radius: 8px; border: 1px solid #e5e7eb; overflow: hidden; display: flex; flex-direction: column;'>
                {$tagImagemCapa}
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

        $adTopo = $this->renderizarBlocoPublicidade('horizontal');
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
                .grid { max-width: 1100px; margin: 30px auto; padding: 0 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; flex-grow: 1; width: 100%; box-sizing: border-box; }
            </style>
        </head>
        <body>
            <header>
                <div class='nav-container'>
                    <h1 style='margin: 0; font-size: 1.5rem;'><a href='/' style='color: white; text-decoration: none;'>Radar Notícias</a></h1>
                    <a href='/admin/dashboard' style='color: #cbd5e1; text-decoration: none; font-size: 0.9rem;'>Área do Redator</a>
                </div>
            </header>

            <div style='max-width: 1100px; width: 100%; margin: 20px auto 0 auto; padding: 0 20px; box-sizing: border-box;'>
                {$adTopo}
            </div>

            <main class='grid'>
                {$cards}
            </main>
            {$footer}
        </body>
        </html>";
    }

    private function renderizarArtigo(array $noticia, array $linksAfiliados = []): string
    {
        $dataPublicacao = date('d/m/Y \à\s H:i', strtotime($noticia['criado_em']));

        $tagImagemDestaque = '';
        if (!empty($noticia['imagem_destaque'])) {
            $tagImagemDestaque = "<div style='margin-bottom: 2rem;'><img src='" . htmlspecialchars($noticia['imagem_destaque']) . "' alt='" . htmlspecialchars($noticia['titulo']) . "' style='width: 100%; max-height: 420px; object-fit: cover; border-radius: 8px;'></div>";
        }

        $playerVideo = $this->renderizarPlayerVideo($noticia['video_url'] ?? null);
        $adArtigo = $this->renderizarBlocoPublicidade('horizontal');

        $seccaoEditorial = '';
        if (!empty($noticia['opiniao_editorial'])) {
            $seccaoEditorial = "
            <aside style='background: #eff6ff; border-left: 4px solid #2563eb; padding: 16px 20px; border-radius: 4px; margin: 2rem 0;'>
                <h4 style='color: #1e40af; margin-top: 0; margin-bottom: 8px;'>Análise do Radar</h4>
                <p style='color: #1e3a8a; line-height: 1.6; margin: 0; font-size: 0.95rem;'>" . nl2br(htmlspecialchars($noticia['opiniao_editorial'])) . "</p>
            </aside>";
        }

        $seccaoAfiliados = '';
        if (!empty($linksAfiliados)) {
            $seccaoAfiliados = "
            <div style='background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 20px; margin: 2rem 0;'>
                <h4 style='margin: 0 0 12px 0; color: #0f172a; font-size: 1rem;'>Recomendações e Divulgação Relacionada</h4>
                <ul style='list-style: none; padding: 0; margin: 0;'>";

            foreach ($linksAfiliados as $link) {
                $seccaoAfiliados .= "
                    <li style='margin-bottom: 10px;'>
                        <a href='/ir/" . (int)$link['id'] . "' target='_blank' rel='nofollow sponsored noopener' style='display: inline-flex; align-items: center; gap: 8px; color: #2563eb; font-weight: 600; text-decoration: none;'>
                            <span style='background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; text-transform: uppercase;'>Parceria</span>
                            " . htmlspecialchars($link['titulo']) . " &rarr;
                        </a>
                    </li>";
            }

            $seccaoAfiliados .= "</ul></div>";
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
                
                <p style='font-size: 1.15rem; color: #4b5563; font-style: italic; border-left: 3px solid #9ca3af; padding-left: 14px; margin-bottom: 30px;'>
                    " . htmlspecialchars($noticia['conteudo_resumo']) . "
                </p>

                {$tagImagemDestaque}
                {$playerVideo}

                <div class='article-body'>
                    " . nl2br(htmlspecialchars($noticia['conteudo_completo'])) . "
                </div>

                {$adArtigo}
                {$seccaoEditorial}
                {$seccaoAfiliados}
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
