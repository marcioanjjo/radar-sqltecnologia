<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;

class NoticiaAdminController
{
    public function index(Request $request, Response $response): Response
    {
        $pdo = Database::getConnection();
        $params = $request->getQueryParams();
        $filtroStatus = $params['status'] ?? 'todos';

        $sql = "SELECT id, titulo, categoria, origem, status, cliques_total, criado_em FROM noticias";
        $binds = [];

        if (in_array($filtroStatus, ['rascunho', 'publicado', 'arquivado'], true)) {
            $sql .= " WHERE status = :status";
            $binds[':status'] = $filtroStatus;
        }

        $sql .= " ORDER BY criado_em DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($binds);
        $noticias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $html = $this->renderizarLista($noticias, $filtroStatus);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function criar(Request $request, Response $response): Response
    {
        $html = $this->renderizarFormularioCriacao();
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function guardar(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $uploadedFiles = $request->getUploadedFiles();

        $titulo           = trim($data['titulo'] ?? '');
        $categoria        = trim($data['categoria'] ?? '');
        $conteudoResumo   = trim($data['conteudo_resumo'] ?? '');
        $conteudoCompleto = trim($data['conteudo_completo'] ?? '');
        $opiniaoEditorial = trim($data['opiniao_editorial'] ?? '');
        $imagemDestaque   = trim($data['imagem_destaque'] ?? '');
        $videoUrl         = trim($data['video_url'] ?? '');
        $status           = in_array($data['status'] ?? '', ['rascunho', 'publicado'], true)
            ? $data['status']
            : 'rascunho';

        if (empty($titulo) || empty($categoria) || empty($conteudoResumo) || empty($conteudoCompleto)) {
            $response->getBody()->write("Preencha todos os campos obrigatórios.");
            return $response->withStatus(400);
        }

        // Processa upload de arquivo local caso tenha sido enviado
        if (isset($uploadedFiles['arquivo_imagem'])) {
            $caminhoUpload = $this->processarUploadImagem($uploadedFiles['arquivo_imagem']);
            if ($caminhoUpload !== null) {
                $imagemDestaque = $caminhoUpload;
            }
        }

        $baseSlug = $this->gerarSlug($titulo);
        $slug = $baseSlug;

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT COUNT(1) FROM noticias WHERE slug = :slug");
        $stmt->execute([':slug' => $slug]);
        if ((int)$stmt->fetchColumn() > 0) {
            $slug .= '-' . time();
        }

        $sql = "INSERT INTO noticias 
                (titulo, slug, categoria, conteudo_resumo, conteudo_completo, opiniao_editorial, imagem_destaque, video_url, origem, status, criado_em)
                VALUES 
                (:titulo, :slug, :categoria, :resumo, :completo, :opiniao, :imagem, :video_url, 'manual', :status, NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':titulo'    => $titulo,
            ':slug'      => $slug,
            ':categoria' => $categoria,
            ':resumo'    => $conteudoResumo,
            ':completo'  => $conteudoCompleto,
            ':opiniao'   => empty($opiniaoEditorial) ? null : $opiniaoEditorial,
            ':imagem'    => empty($imagemDestaque) ? null : $imagemDestaque,
            ':video_url' => empty($videoUrl) ? null : $videoUrl,
            ':status'    => $status
        ]);

        return $response->withHeader('Location', '/admin/noticias')->withStatus(302);
    }

    public function editar(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM noticias WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $noticia = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$noticia) {
            $response->getBody()->write("Notícia não encontrada.");
            return $response->withStatus(404);
        }

        $html = $this->renderizarFormulario($noticia);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function atualizar(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $data = (array) $request->getParsedBody();
        $uploadedFiles = $request->getUploadedFiles();

        $titulo           = trim($data['titulo'] ?? '');
        $categoria        = trim($data['categoria'] ?? '');
        $conteudoResumo   = trim($data['conteudo_resumo'] ?? '');
        $conteudoCompleto = trim($data['conteudo_completo'] ?? '');
        $opiniaoEditorial = trim($data['opiniao_editorial'] ?? '');
        $imagemDestaque   = trim($data['imagem_destaque'] ?? '');
        $videoUrl         = trim($data['video_url'] ?? '');
        $status           = in_array($data['status'] ?? '', ['rascunho', 'publicado', 'arquivado'], true)
            ? $data['status']
            : 'rascunho';

        // Processa upload de imagem caso o usuário tenha subido um novo arquivo
        if (isset($uploadedFiles['arquivo_imagem'])) {
            $caminhoUpload = $this->processarUploadImagem($uploadedFiles['arquivo_imagem']);
            if ($caminhoUpload !== null) {
                $imagemDestaque = $caminhoUpload;
            }
        }

        $pdo = Database::getConnection();
        $sql = "UPDATE noticias SET 
                    titulo = :titulo,
                    categoria = :categoria,
                    conteudo_resumo = :resumo,
                    conteudo_completo = :completo,
                    opiniao_editorial = :opiniao,
                    imagem_destaque = :imagem,
                    video_url = :video_url,
                    status = :status,
                    atualizado_em = NOW()
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':titulo'    => $titulo,
            ':categoria' => $categoria,
            ':resumo'    => $conteudoResumo,
            ':completo'  => $conteudoCompleto,
            ':opiniao'   => empty($opiniaoEditorial) ? null : $opiniaoEditorial,
            ':imagem'    => empty($imagemDestaque) ? null : $imagemDestaque,
            ':video_url' => empty($videoUrl) ? null : $videoUrl,
            ':status'    => $status,
            ':id'        => $id
        ]);

        return $response->withHeader('Location', '/admin/noticias')->withStatus(302);
    }

    private function processarUploadImagem(UploadedFileInterface $arquivo): ?string
    {
        if ($arquivo->getError() !== UPLOAD_ERR_OK) {
            return null;
        }

        $extensao = strtolower(pathinfo($arquivo->getClientFilename() ?? '', PATHINFO_EXTENSION));
        $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (!in_array($extensao, $extensoesPermitidas, true)) {
            return null;
        }

        $diretorioUpload = __DIR__ . '/../../public/uploads';
        if (!is_dir($diretorioUpload)) {
            mkdir($diretorioUpload, 0755, true);
        }

        $nomeArquivo = sprintf('img_%s_%s.%s', date('Ymd_His'), bin2hex(random_bytes(4)), $extensao);
        $caminhoFisico = $diretorioUpload . DIRECTORY_SEPARATOR . $nomeArquivo;

        $arquivo->moveTo($caminhoFisico);

        return '/uploads/' . $nomeArquivo;
    }

    private function gerarSlug(string $texto): string
    {
        $slug = mb_strtolower($texto, 'UTF-8');
        $slug = preg_replace('~[^\pL\d]+~u', '-', $slug);
        $slug = iconv('utf-8', 'us-ascii//TRANSLIT', $slug);
        $slug = preg_replace('~[^-\w]+~', '', $slug);
        $slug = trim($slug, '-');
        return preg_replace('~-+~', '-', $slug) ?: 'noticia-' . time();
    }

    private function renderizarLista(array $noticias, string $filtro): string
    {
        $linhas = '';
        foreach ($noticias as $item) {
            $corStatus = $item['status'] === 'publicado' ? '#16a34a' : '#d97706';
            $linhas .= "
            <tr style='border-bottom: 1px solid #e5e7eb;'>
                <td style='padding: 12px;'>" . (int)$item['id'] . "</td>
                <td style='padding: 12px; font-weight: 500;'>" . htmlspecialchars($item['titulo']) . "</td>
                <td style='padding: 12px;'><span style='background: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 4px; font-size: 0.85rem;'>" . htmlspecialchars($item['categoria']) . "</span></td>
                <td style='padding: 12px;'><span style='color: {$corStatus}; font-weight: bold; font-size: 0.85rem; text-transform: uppercase;'>" . htmlspecialchars($item['status']) . "</span></td>
                <td style='padding: 12px; font-size: 0.9rem; color: #6b7280;'>" . htmlspecialchars($item['origem']) . "</td>
                <td style='padding: 12px;'>" . (int)$item['cliques_total'] . "</td>
                <td style='padding: 12px;'>
                    <a href='/admin/noticias/" . (int)$item['id'] . "/editar' style='color: #2563eb; text-decoration: none; font-weight: bold;'>Rever / Editar</a>
                </td>
            </tr>";
        }

        if (empty($linhas)) {
            $linhas = "<tr><td colspan='7' style='padding: 24px; text-align: center; color: #6b7280;'>Nenhuma notícia registada neste estado.</td></tr>";
        }

        return "
        <!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='UTF-8'>
            <title>Redação - Radar Notícias</title>
            <style>
                body { font-family: system-ui, -apple-system, sans-serif; background: #f9fafb; margin: 0; padding: 24px; color: #111827; }
                .container { max-width: 1100px; margin: 0 auto; background: white; padding: 24px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
                .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; padding-bottom: 16px; margin-bottom: 20px; }
                .nav-links a { margin-right: 15px; color: #4b5563; text-decoration: none; font-weight: 500; }
                .nav-links a.active { color: #2563eb; font-weight: bold; border-bottom: 2px solid #2563eb; padding-bottom: 4px; }
                table { width: 100%; border-collapse: collapse; text-align: left; }
                th { padding: 12px; background: #f3f4f6; color: #4b5563; font-size: 0.85rem; text-transform: uppercase; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div>
                        <h2 style='margin: 0;'>Redação Editorial</h2>
                        <small style='color: #6b7280;'>Gestão de Conteúdo e Controlo de Qualidade</small>
                    </div>
                    <div>
                        <a href='/admin/dashboard' style='margin-right: 15px; color: #4b5563; text-decoration: none;'>Dashboard</a>
                        <a href='/admin/logout' style='color: #dc2626; text-decoration: none;'>Sair</a>
                    </div>
                </div>

                <div style='display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;'>
                    <div class='nav-links'>
                        <a href='/admin/noticias' class='" . ($filtro === 'todos' ? 'active' : '') . "'>Todas</a>
                        <a href='/admin/noticias?status=rascunho' class='" . ($filtro === 'rascunho' ? 'active' : '') . "'>Rascunhos (Pendentes)</a>
                        <a href='/admin/noticias?status=publicado' class='" . ($filtro === 'publicado' ? 'active' : '') . "'>Publicadas</a>
                    </div>
                    <a href='/admin/noticias/nova' style='background: #16a34a; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 0.9rem;'>+ Nova Notícia</a>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Título</th>
                            <th>Categoria</th>
                            <th>Estado</th>
                            <th>Origem</th>
                            <th>Cliques</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$linhas}
                    </tbody>
                </table>
            </div>
        </body>
        </html>";
    }

    private function renderizarFormulario(array $noticia): string
    {
        $selRascunho  = $noticia['status'] === 'rascunho' ? 'selected' : '';
        $selPublicado = $noticia['status'] === 'publicado' ? 'selected' : '';
        $selArquivado = $noticia['status'] === 'arquivado' ? 'selected' : '';

        $previewImagem = '';
        if (!empty($noticia['imagem_destaque'])) {
            $previewImagem = "
            <div style='margin-top: 8px;'>
                <small style='color: #6b7280; display: block;'>Imagem atual cadastrada:</small>
                <img src='" . htmlspecialchars($noticia['imagem_destaque']) . "' alt='Preview' style='max-height: 90px; border-radius: 4px; border: 1px solid #d1d5db; margin-top: 4px;'>
            </div>";
        }

        return "
        <!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='UTF-8'>
            <title>Editar Matéria - Radar Notícias</title>
            <style>
                body { font-family: system-ui, -apple-system, sans-serif; background: #f9fafb; margin: 0; padding: 24px; color: #111827; }
                .container { max-width: 900px; margin: 0 auto; background: white; padding: 28px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
                .field { margin-bottom: 20px; }
                label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.9rem; }
                input[type='text'], input[type='file'], select, textarea { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box; font-family: inherit; }
                textarea { resize: vertical; }
                .editorial-box { background: #eff6ff; border: 1px solid #bfdbfe; padding: 16px; border-radius: 6px; }
                .media-box { background: #faf5ff; border: 1px solid #e9d5ff; padding: 16px; border-radius: 6px; }
                .btn { background: #2563eb; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; }
                .btn:hover { background: #1d4ed8; }
                .btn-cancel { color: #4b5563; text-decoration: none; margin-left: 15px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Rever e Enriquecer Matéria</h2>
                <form action='/admin/noticias/" . (int)$noticia['id'] . "/atualizar' method='POST' enctype='multipart/form-data'>
                    <div class='field'>
                        <label>Título da Notícia</label>
                        <input type='text' name='titulo' value='" . htmlspecialchars($noticia['titulo']) . "' required>
                    </div>

                    <div style='display: flex; gap: 20px;'>
                        <div class='field' style='flex: 1;'>
                            <label>Categoria</label>
                            <input type='text' name='categoria' value='" . htmlspecialchars($noticia['categoria']) . "' required>
                        </div>
                        <div class='field' style='flex: 1;'>
                            <label>Estado de Publicação</label>
                            <select name='status'>
                                <option value='rascunho' {$selRascunho}>Rascunho (Não visível ao público)</option>
                                <option value='publicado' {$selPublicado}>Publicado (Visível no Portal)</option>
                                <option value='arquivado' {$selArquivado}>Arquivado</option>
                            </select>
                        </div>
                    </div>

                    <div class='field media-box'>
                        <label style='color: #6b21a8; font-weight: bold;'>Mídias do Artigo (Imagens e Vídeos)</label>
                        
                        <div style='display: flex; gap: 15px; margin-top: 10px;'>
                            <div style='flex: 1;'>
                                <label style='font-size: 0.8rem; color: #7e22ce;'>Enviar Arquivo de Imagem Local (Do PC)</label>
                                <input type='file' name='arquivo_imagem' accept='image/*'>
                            </div>
                            <div style='flex: 1;'>
                                <label style='font-size: 0.8rem; color: #7e22ce;'>Ou URL da Imagem de Destaque</label>
                                <input type='text' name='imagem_destaque' value='" . htmlspecialchars($noticia['imagem_destaque'] ?? '') . "' placeholder='https://exemplo.com/imagem.jpg'>
                            </div>
                        </div>
                        {$previewImagem}

                        <div style='margin-top: 14px;'>
                            <label style='font-size: 0.8rem; color: #7e22ce;'>Vídeo (YouTube ou MP4 direto)</label>
                            <input type='text' name='video_url' value='" . htmlspecialchars($noticia['video_url'] ?? '') . "' placeholder='https://www.youtube.com/watch?v=...'>
                        </div>
                    </div>

                    <div class='field'>
                        <label>Resumo Curto (Apresentação / Meta Description)</label>
                        <textarea name='conteudo_resumo' rows='3' required>" . htmlspecialchars($noticia['conteudo_resumo']) . "</textarea>
                    </div>

                    <div class='field'>
                        <label>Conteúdo Principal (Corpo do Artigo)</label>
                        <textarea name='conteudo_completo' rows='8' required>" . htmlspecialchars($noticia['conteudo_completo']) . "</textarea>
                    </div>

                    <div class='field editorial-box'>
                        <label style='color: #1e40af;'>Análise Editorial Própria (AdSense Compliance)</label>
                        <small style='display: block; color: #3b82f6; margin-bottom: 8px;'>Adicione a sua análise técnica, impacto de mercado ou conclusão autoral.</small>
                        <textarea name='opiniao_editorial' rows='4' placeholder='Insira a sua visão técnica sobre esta notícia...'>" . htmlspecialchars($noticia['opiniao_editorial'] ?? '') . "</textarea>
                    </div>

                    <div style='margin-top: 24px;'>
                        <button type='submit' class='btn'>Guardar Alterações</button>
                        <a href='/admin/noticias' class='btn-cancel'>Voltar à Lista</a>
                    </div>
                </form>
            </div>
        </body>
        </html>";
    }

    private function renderizarFormularioCriacao(): string
    {
        return "
        <!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='UTF-8'>
            <title>Nova Notícia - Radar Notícias</title>
            <style>
                body { font-family: system-ui, -apple-system, sans-serif; background: #f9fafb; margin: 0; padding: 24px; color: #111827; }
                .container { max-width: 900px; margin: 0 auto; background: white; padding: 28px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
                .field { margin-bottom: 20px; }
                label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.9rem; }
                input[type='text'], input[type='file'], select, textarea { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box; font-family: inherit; }
                textarea { resize: vertical; }
                .editorial-box { background: #f0fdf4; border: 1px solid #bbf7d0; padding: 16px; border-radius: 6px; }
                .media-box { background: #faf5ff; border: 1px solid #e9d5ff; padding: 16px; border-radius: 6px; }
                .btn { background: #16a34a; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; }
                .btn:hover { background: #15803d; }
                .btn-cancel { color: #4b5563; text-decoration: none; margin-left: 15px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Redigir Nova Notícia Autoral</h2>
                <form action='/admin/noticias/guardar' method='POST' enctype='multipart/form-data'>
                    <div class='field'>
                        <label>Título da Notícia *</label>
                        <input type='text' name='titulo' placeholder='Ex: Nova atualização do ecossistema PHP foca em performance' required>
                    </div>

                    <div style='display: flex; gap: 20px;'>
                        <div class='field' style='flex: 1;'>
                            <label>Categoria *</label>
                            <input type='text' name='categoria' placeholder='Ex: Tecnologia, Desenvolvimento, IA' required>
                        </div>
                        <div class='field' style='flex: 1;'>
                            <label>Estado de Publicação</label>
                            <select name='status'>
                                <option value='rascunho'>Rascunho (Salvar para rever depois)</option>
                                <option value='publicado'>Publicar Imediatamente</option>
                            </select>
                        </div>
                    </div>

                    <div class='field media-box'>
                        <label style='color: #6b21a8; font-weight: bold;'>Mídias do Artigo (Imagens e Vídeos)</label>
                        
                        <div style='display: flex; gap: 15px; margin-top: 10px;'>
                            <div style='flex: 1;'>
                                <label style='font-size: 0.8rem; color: #7e22ce;'>Enviar Arquivo de Imagem Local (Do PC)</label>
                                <input type='file' name='arquivo_imagem' accept='image/*'>
                            </div>
                            <div style='flex: 1;'>
                                <label style='font-size: 0.8rem; color: #7e22ce;'>Ou URL da Imagem de Destaque</label>
                                <input type='text' name='imagem_destaque' placeholder='https://exemplo.com/imagem.jpg'>
                            </div>
                        </div>

                        <div style='margin-top: 14px;'>
                            <label style='font-size: 0.8rem; color: #7e22ce;'>Vídeo (YouTube ou MP4 direto)</label>
                            <input type='text' name='video_url' placeholder='https://www.youtube.com/watch?v=...'>
                        </div>
                    </div>

                    <div class='field'>
                        <label>Resumo Curto (SEO / Chamada de Capa) *</label>
                        <textarea name='conteudo_resumo' rows='3' placeholder='Breve descrição do tema...' required></textarea>
                    </div>

                    <div class='field'>
                        <label>Conteúdo Completo da Matéria *</label>
                        <textarea name='conteudo_completo' rows='10' placeholder='Desenvolva o corpo principal do artigo aqui...' required></textarea>
                    </div>

                    <div class='field editorial-box'>
                        <label style='color: #166534;'>Parecer do Editor / Considerações Finais</label>
                        <textarea name='opiniao_editorial' rows='4' placeholder='Comentários analíticos próprios...'></textarea>
                    </div>

                    <div style='margin-top: 24px;'>
                        <button type='submit' class='btn'>Criar Matéria</button>
                        <a href='/admin/noticias' class='btn-cancel'>Cancelar</a>
                    </div>
                </form>
            </div>
        </body>
        </html>";
    }
}
