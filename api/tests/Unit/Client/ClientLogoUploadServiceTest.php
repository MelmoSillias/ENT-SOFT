<?php

namespace App\Tests\Unit\Client;

use App\Client\Application\Dto\ClientDetailResponseDto;
use App\Client\Application\Dto\ClientResponseDto;
use App\Client\Application\Service\ClientLogoUploadService;
use App\Client\Domain\Entity\Client;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Validator\Validation;

final class ClientLogoUploadServiceTest extends TestCase
{
    private string $root;
    private ClientLogoUploadService $logos;
    private ClientRepositoryInterface $clients;
    private Client $client;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/ent-client-logo-'.bin2hex(random_bytes(8));
        mkdir($this->root, 0777, true);
        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($this->root);
        $this->clients = $this->createMock(ClientRepositoryInterface::class);
        $this->logos = new ClientLogoUploadService($this->clients, Validation::createValidator(), $kernel);
        $this->client = new Client('CLI-1', 'Client logo');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->root);
    }

    private function image(): UploadedFile
    {
        $path = $this->root.'/incoming-'.bin2hex(random_bytes(4));
        $image = imagecreatetruecolor(2, 2);
        imagepng($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, '../../logo.php', 'application/x-php', null, true);
    }

    public function testUploadReplacementDeletionAndDtoContract(): void
    {
        self::assertNull(ClientResponseDto::fromEntity($this->client)->toArray()['logoUrl']);
        $this->clients->expects(self::exactly(4))->method('save')->with($this->client);
        $this->logos->upload($this->client, $this->image());
        $first = $this->client->getLogoUrl();
        self::assertStringStartsWith('/uploads/client-logos/'.$this->client->getId().'-', $first);
        self::assertStringEndsWith('.png', $first);
        self::assertFileExists($this->root.'/public'.$first);
        self::assertStringStartsWith('data:image/png;base64,', $this->logos->resolveForDocuments($first));
        self::assertSame($first, ClientResponseDto::fromEntity($this->client)->toArray()['logoUrl']);
        self::assertSame($first, ClientDetailResponseDto::fromEntity($this->client, 0, 0, [])->toArray()['logoUrl']);
        $this->logos->upload($this->client, $this->image());
        $second = $this->client->getLogoUrl();
        self::assertNotSame($first, $second);
        self::assertFileDoesNotExist($this->root.'/public'.$first);
        self::assertFileExists($this->root.'/public'.$second);
        $this->logos->clear($this->client);
        self::assertNull($this->client->getLogoUrl());
        self::assertFileDoesNotExist($this->root.'/public'.$second);
        $this->logos->clear($this->client);
    }

    public function testFailedPersistencePreservesPreviousLogoAndRemovesNewFile(): void
    {
        $this->logos->upload($this->client, $this->image());
        $oldUrl = $this->client->getLogoUrl();
        $this->clients->method('save')->willThrowException(new \RuntimeException('Persistence failed'));
        foreach (['upload', 'clear'] as $operation) {
            try {
                $operation === 'upload' ? $this->logos->upload($this->client, $this->image()) : $this->logos->clear($this->client);
                self::fail('Expected persistence failure');
            } catch (\RuntimeException $e) {
                self::assertSame('Persistence failed', $e->getMessage());
            }
            self::assertSame($oldUrl, $this->client->getLogoUrl());
            self::assertFileExists($this->root.'/public'.$oldUrl);
            self::assertCount(1, glob($this->root.'/public/uploads/client-logos/*'));
        }
    }

    public static function invalidImages(): iterable
    {
        yield 'SVG' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', null];
        yield 'PHP disguised as PNG' => ['<?php echo "bad";', null];
        yield 'truncated PNG' => [base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAAB'), null];
        yield 'oversized' => [str_repeat('X', 2 * 1024 * 1024 + 1), null];
        yield 'incomplete upload' => ['image', UPLOAD_ERR_PARTIAL];
    }

    #[DataProvider('invalidImages')]
    public function testRejectsUnsafeImagesWithoutSaving(string $content, ?int $error): void
    {
        $path = $this->root.'/invalid';
        file_put_contents($path, $content);
        $this->clients->expects(self::never())->method('save');
        $this->expectException(\InvalidArgumentException::class);
        $this->logos->upload($this->client, new UploadedFile($path, 'logo.png', 'image/png', $error, true));
    }

    public function testRejectsExcessiveDimensions(): void
    {
        $path = $this->root.'/wide.png';
        $image = imagecreatetruecolor(4097, 1);
        imagepng($image, $path);
        imagedestroy($image);
        $this->clients->expects(self::never())->method('save');
        $this->expectException(\InvalidArgumentException::class);
        $this->logos->upload($this->client, new UploadedFile($path, 'wide.png', null, null, true));
    }

    public function testDocumentResolutionOnlyReadsDedicatedLocalImages(): void
    {
        $this->logos->upload($this->client, $this->image());
        $url = $this->client->getLogoUrl();
        foreach ([null, '', 'https://example.com/logo.png', 'data:image/png;base64,AA==', '/uploads/documents/logo.png', '/uploads/client-logos/../../secret.png', $url.'?v=1', str_replace('.png', '.svg', $url)] as $unsafe) {
            self::assertNull($this->logos->resolveForDocuments($unsafe));
        }
        $path = $this->root.'/public'.$url;
        file_put_contents($path, '<svg/>');
        self::assertNull($this->logos->resolveForDocuments($url));
        unlink($path);
        self::assertNull($this->logos->resolveForDocuments($url));
    }
}
