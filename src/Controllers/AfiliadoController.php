<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AfiliadoController
{
    // Rota pública de redirecionamento: /ir/{id}
    public function redirecionar(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT id, url_destino, noticia_id FROM links_afiliados WHERE id = :id AND ativo = 1 LIMIT 1");
        $stmt->execute([':id' => $id]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$link) {
            $response->getBody()->write("Link expirado ou inexistente.");
            return $response->withStatus(404);
        }

        // 1. Incrementa o contador total do link
        $stmtUpdate = $pdo->prepare("UPDATE links_afiliados SET cliques_total = cliques_total + 1 WHERE id = :id");
        $stmtUpdate->execute([':id' => $id]);

        // 2. Regista métrica detalhada na tabela logs_cliques
        try {
            $serverParams = $request->getServerParams();
            $ip = $serverParams['REMOTE_ADDR'] ?? '0.0.0.0';
            $ipHash = hash('sha256', $ip);
            $userAgent = substr($serverParams['HTTP_USER_AGENT'] ?? '', 0, 255);

            $stmtLog = $pdo->prepare("
                INSERT INTO logs_cliques (noticia_id, tipo_origem, ip_hash, user_agent, clicado_em) 
                VALUES (:noticia_id, 'afiliado', :ip_hash, :user_agent, NOW())
            ");
            $stmtLog->execute([
                ':noticia_id' => $link['noticia_id'],
                ':ip_hash'    => $ipHash,
                ':user_agent' => $userAgent
            ]);
        } catch (\Throwable $e) {
            // Registo silencioso em caso de anomalia de log
        }

        // 3. Executa o redirecionamento 302 para a URL final
        return $response
            ->withHeader('Location', $link['url_destino'])
            ->withStatus(302);
    }

    // Listagem dos links no painel administrativo: /admin/afiliados
    public function listar(Request $request, Response $response): Response
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("
            SELECT a.*, n.titulo AS noticia_titulo 
            FROM links_afiliados a
            LEFT JOIN noticias n ON n.id = a.noticia_id
            ORDER BY a.criado_em DESC
        ");
        $links = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $linhas = '';
        foreach ($links as $item) {
            $urlRedirecionamento = "http://localhost:8080/ir/" . (int)$item['id'];
            $status = $item['ativo'] ? '<span style="color: #16a34a; font-weight: bold;">Ativo</span>' : '<span style="color: #dc2626; font-weight: bold;">Inativo</span>';
            $nomeNoticia = $item['noticia_titulo'] ? htmlspecialchars($item['noticia_titulo']) : '<em>Geral (Sem notícia vinculada)</em>';

            $linhas .= "
            <tr style='border-bottom: 1px solid #e5e7eb;'>
                <td style='padding: 12px;'>" . (int)$item['id'] . "</td>
                <td style='padding: 12px; font-weight: 600;'>" . htmlspecialchars($item['titulo']) . "</td>
                <td style='padding: 12px; font-size: 0.9rem; color: #4b5563;'>{$nomeNoticia}</td>
                <td style='padding: 12px; font-family: monospace; font-size: 0.85rem;'><a href='{$urlRedirecionamento}' target='_blank' style='color: #2563eb;'>{$urlRedirecionamento}</a></td>
                <td style='padding: 12px; font-weight: bold;'>" . (int)$item['cliques_total'] . "</td>
                <td style='padding: 12px;'>{$status}</td>
            </tr>";
        }

        if (empty($linhas)) {
            $linhas = "<tr><td colspan='6' style='padding: 24px; text-align: center; color: #6b7280;'>Nenhum link de afiliado cadastrado.</td></tr>";
        }

        $html = "
        <!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='UTF-8'>
            <title>Links de Afiliados - Radar Notícias</title>
            <style>
                body { font-family: system-ui, -apple-system, sans-serif; background: #f9fafb; margin: 0; padding: 24px; color: #111827; }
                .container { max-width: 1100px; margin: 0 auto; background: white; padding: 24px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
                .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; padding-bottom: 16px; margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; text-align: left; }
                th { padding: 12px; background: #f3f4f6; color: #4b5563; font-size: 0.85rem; text-transform: uppercase; }
                .btn { background: #2563eb; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 0.9rem; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div>
                        <h2 style='margin: 0;'>Links de Afiliados & Parcerias</h2>
                        <small style='color: #6b7280;'>Rastreamento de conversão e cliques com código 302</small>
                    </div>
                    <div>
                        <a href='/admin/afiliados/novo' class='btn'>+ Novo Link</a>
                        <a href='/admin/dashboard' style='margin-left: 15px; color: #4b5563; text-decoration: none;'>Dashboard</a>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Rótulo / Campanha</th>
                            <th>Notícia Vinculada</th>
                            <th>Link Interno Encurtado</th>
                            <th>Cliques</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$linhas}
                    </tbody>
                </table>
            </div>
        </body>
        </html>";

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    // Formulário para registar novo link: /admin/afiliados/novo
    public function criar(Request $request, Response $response): Response
    {
        $pdo = Database::getConnection();
        $noticias = $pdo->query("SELECT id, titulo FROM noticias ORDER BY criado_em DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

        $opcoesNoticias = "<option value=''>-- Nenhum vínculo específico (Campanha Global) --</option>";
        foreach ($noticias as $noticia) {
            $opcoesNoticias .= "<option value='" . (int)$noticia['id'] . "'>" . htmlspecialchars($noticia['titulo']) . "</option>";
        }

        $html = "
        <!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='UTF-8'>
            <title>Novo Link de Afiliado - Radar Notícias</title>
            <style>
                body { font-family: system-ui, -apple-system, sans-serif; background: #f9fafb; margin: 0; padding: 24px; color: #111827; }
                .container { max-width: 700px; margin: 0 auto; background: white; padding: 28px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
                .field { margin-bottom: 20px; }
                label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.9rem; }
                input[type='text'], select { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box; font-family: inherit; }
                .btn { background: #16a34a; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; }
                .btn:hover { background: #15803d; }
                .btn-cancel { color: #4b5563; text-decoration: none; margin-left: 15px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Cadastrar Link de Afiliado</h2>
                <form action='/admin/afiliados/guardar' method='POST'>
                    <div class='field'>
                        <label>Rótulo / Identificação da Campanha *</label>
                        <input type='text' name='titulo' placeholder='Ex: Curso de PHP Completo na Udemy' required>
                    </div>

                    <div class='field'>
                        <label>URL de Destino Original (Com a sua tag de afiliado) *</label>
                        <input type='text' name='url_destino' placeholder='https://parceiro.com/produto?tag=sua_tag_afiliado' required>
                    </div>

                    <div class='field'>
                        <label>Vincular a uma Notícia Existente (Opcional)</label>
                        <select name='noticia_id'>
                            {$opcoesNoticias}
                        </select>
                    </div>

                    <div style='margin-top: 24px;'>
                        <button type='submit' class='btn'>Gerar Link Rastreado</button>
                        <a href='/admin/afiliados' class='btn-cancel'>Cancelar</a>
                    </div>
                </form>
            </div>
        </body>
        </html>";

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    // Processa a inserção do link no MySQL: /admin/afiliados/guardar
    public function guardar(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $titulo = trim($data['titulo'] ?? '');
        $urlDestino = trim($data['url_destino'] ?? '');
        $noticiaId = !empty($data['noticia_id']) ? (int)$data['noticia_id'] : null;

        if (empty($titulo) || empty($urlDestino)) {
            $response->getBody()->write("Preencha todos os campos obrigatórios.");
            return $response->withStatus(400);
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO links_afiliados (noticia_id, titulo, url_destino, cliques_total, ativo, criado_em)
            VALUES (:noticia_id, :titulo, :url_destino, 0, 1, NOW())
        ");
        $stmt->execute([
            ':noticia_id'  => $noticiaId,
            ':titulo'      => $titulo,
            ':url_destino' => $urlDestino
        ]);

        return $response->withHeader('Location', '/admin/afiliados')->withStatus(302);
    }
}
