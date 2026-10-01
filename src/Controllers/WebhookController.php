<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class WebhookController
{
    public function receberNoticia(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        // 1. Validação básica dos campos obrigatórios enviados pelo Spark
        $camposObrigatorios = ['titulo', 'conteudo_resumo', 'categoria'];
        foreach ($camposObrigatorios as $campo) {
            if (empty($data[$campo]) || !is_string($data[$campo])) {
                return $this->jsonResponse($response, 400, [
                    'status' => 'erro',
                    'mensagem' => "O campo '{$campo}' é obrigatório e deve ser preenchido."
                ]);
            }
        }

        $titulo         = trim($data['titulo']);
        $conteudoResumo = trim($data['conteudo_resumo']);
        $conteudoCompleto = trim($data['conteudo_completo'] ?? $conteudoResumo);
        $categoria      = trim($data['categoria']);
        $urlFonte       = trim($data['url_fonte'] ?? '');
        $imagemDestaque = trim($data['imagem_destaque'] ?? '');

        // 2. Geração do slug amigável
        $slug = $this->gerarSlug($titulo);

        // 3. Persistência no banco de dados via PDO Singleton
        try {
            $pdo = Database::getConnection();

            // Garante slug único adicionando sufixo numérico se necessário
            $slugFinal = $this->obterSlugUnico($pdo, $slug);

            $sql = "INSERT INTO noticias 
                    (titulo, slug, categoria, conteudo_resumo, conteudo_completo, url_fonte, imagem_destaque, origem, status, criado_em) 
                    VALUES 
                    (:titulo, :slug, :categoria, :resumo, :completo, :fonte, :imagem, 'gemini_spark', 'rascunho', NOW())";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':titulo'    => $titulo,
                ':slug'      => $slugFinal,
                ':categoria' => $categoria,
                ':resumo'    => $conteudoResumo,
                ':completo'  => $conteudoCompleto,
                ':fonte'     => $urlFonte,
                ':imagem'    => $imagemDestaque
            ]);

            $noticiaId = (int) $pdo->lastInsertId();

            return $this->jsonResponse($response, 201, [
                'status'    => 'sucesso',
                'mensagem'  => 'Notícia processada e salva com sucesso!',
                'noticia_id' => $noticiaId,
                'slug'      => $slugFinal
            ]);
        } catch (\Throwable $e) {
            return $this->jsonResponse($response, 500, [
                'status'   => 'erro',
                'mensagem' => 'Erro interno ao salvar notícia: ' . $e->getMessage()
            ]);
        }
    }

    private function gerarSlug(string $texto): string
    {
        // Translitera caracteres acentuados para ASCII
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        $slug = preg_replace('~[^\pL\d]+~u', '-', $slug);
        $slug = trim($slug, '-');
        $slug = strtolower($slug);
        return empty($slug) ? 'noticia-' . bin2hex(random_bytes(4)) : $slug;
    }

    private function obterSlugUnico(PDO $pdo, string $slug): string
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM noticias WHERE slug = :slug");
        $stmt->execute([':slug' => $slug]);
        $count = (int) $stmt->fetchColumn();

        if ($count > 0) {
            return $slug . '-' . time();
        }

        return $slug;
    }

    private function jsonResponse(Response $response, int $statusCode, array $payload): Response
    {
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }
}
