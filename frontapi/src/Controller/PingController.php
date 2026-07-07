<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Json;
use App\Support\Database;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * 인증 확인용 보호 엔드포인트.
 *
 * JwtAuthMiddleware 통과 시 주입된 사용자 정보를 돌려주고, PDO prepared statement 로
 * 서버 시각을 조회해 바인딩 동작을 함께 확인한다.
 */
final class PingController
{
    public function __construct(
        private readonly Json $json,
        private readonly Database $db,
    ) {
    }

    #[OA\Get(path: '/api/v1/ping', summary: '인증 확인(Bearer)', security: [['bearerAuth' => []]], tags: ['System'], responses: [
        new OA\Response(response: 200, description: '인증된 사용자 정보'),
        new OA\Response(response: 401, description: '인증 실패'),
    ])]
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        // prepared statement 바인딩 예시(raw 문자열 조합 금지)
        $stmt = $this->db->pdo()->prepare('SELECT ? AS echoed, NOW() AS server_time');
        $stmt->execute(['pong']);
        /** @var array{echoed:string, server_time:string} $row */
        $row = $stmt->fetch();

        return $this->json->success([
            'echoed'      => $row['echoed'],
            'server_time' => $row['server_time'],
            'user_id'     => $request->getAttribute('userId'),
            'role'        => $request->getAttribute('role'),
        ]);
    }
}
