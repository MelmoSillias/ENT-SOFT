<?php

namespace App\Tests\Integration;

use App\AccessAudit\Domain\Entity\Permission;
use App\AccessAudit\Domain\Entity\UtilisateurPermission;
use App\Client\Application\Service\ClientLogoUploadService;
use App\Client\Domain\Entity\Client;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\IdentityAccess\Application\Service\AccessTokenService;
use App\IdentityAccess\Domain\Entity\Utilisateur;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ClientLogoApiTest extends WebTestCase
{
    private KernelBrowser $browser;
    private EntityManagerInterface $em;
    private Utilisateur $user;
    private Client $client;
    private string $root;
    private string $base;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();
        $container = self::getContainer();
        if (!\Doctrine\DBAL\Types\Type::hasType('uuid')) {
            \Doctrine\DBAL\Types\Type::addType('uuid', \Symfony\Bridge\Doctrine\Types\UuidType::class);
        }
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $container->set('doctrine.dbal.default_connection', $connection);
        $this->em = $container->get(EntityManagerInterface::class);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->user = new Utilisateur('Test', 'Logo', '12345678', 'test-client-logo', 'unused', 'TEST_NO_DEFAULTS');
        $this->client = new Client('CLI-LOGO', 'Client logo');
        $this->em->persist($this->user);
        $this->em->persist($this->client);
        $this->em->flush();
        $this->base = '/api/clients/'.$this->client->getId();
        $this->root = sys_get_temp_dir().'/ent-client-logo-api-'.bin2hex(random_bytes(8));
        mkdir($this->root, 0777, true);
        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($this->root);
        $container->set(ClientLogoUploadService::class, new ClientLogoUploadService($container->get(ClientRepositoryInterface::class), $container->get(ValidatorInterface::class), $kernel));
        $token = $container->get(AccessTokenService::class)->create($this->user);
        $this->browser->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            (new Filesystem())->remove($this->root);
        }
        parent::tearDown();
    }

    private function grant(string ...$codes): void
    {
        foreach ($codes as $code) {
            $permission = new Permission($code, $code, 'client');
            $this->em->persist($permission);
            $this->em->persist(new UtilisateurPermission($this->user->getId(), $permission, true, $this->user->getId()));
        }
        $this->em->flush();
    }

    private function upload(): array
    {
        $path = $this->root.'/incoming.png';
        $image = imagecreatetruecolor(2, 2);
        imagepng($image, $path);
        imagedestroy($image);
        $this->browser->request('POST', $this->base.'/logo', [], ['file' => new UploadedFile($path, 'logo.png', 'image/png', null, true)]);

        return json_decode($this->browser->getResponse()->getContent(), true);
    }

    public function testUpdatePermissionIsRequiredForBothLogoEndpoints(): void
    {
        $this->grant('client.clients.view', 'client.clients.create');
        $this->upload();
        self::assertResponseStatusCodeSame(403);
        $this->browser->request('DELETE', $this->base.'/logo');
        self::assertResponseStatusCodeSame(403);
        self::assertNull($this->client->getLogoUrl());
    }

    public function testLogoLifecycleAndDetailsListContract(): void
    {
        $this->grant('client.clients.update', 'client.clients.view');
        $first = $this->upload()['logoUrl'];
        self::assertResponseIsSuccessful();
        self::assertFileExists($this->root.'/public'.$first);
        $this->em->clear();
        self::assertSame($first, $this->em->find(Client::class, $this->client->getId())->getLogoUrl());
        foreach ([$this->base, $this->base.'/detail', '/api/clients'] as $uri) {
            $this->browser->request('GET', $uri);
            self::assertResponseIsSuccessful();
            $data = json_decode($this->browser->getResponse()->getContent(), true);
            self::assertSame($first, $uri === '/api/clients' ? ($data['items'] ?? $data)[0]['logoUrl'] : $data['logoUrl']);
        }
        $second = $this->upload()['logoUrl'];
        self::assertResponseIsSuccessful();
        self::assertNotSame($first, $second);
        self::assertFileDoesNotExist($this->root.'/public'.$first);
        $this->browser->request('DELETE', $this->base.'/logo');
        self::assertResponseIsSuccessful();
        self::assertNull(json_decode($this->browser->getResponse()->getContent(), true)['logoUrl']);
        self::assertFileDoesNotExist($this->root.'/public'.$second);
        $this->em->clear();
        self::assertNull($this->em->find(Client::class, $this->client->getId())->getLogoUrl());
        $this->browser->request('DELETE', $this->base.'/logo');
        self::assertResponseIsSuccessful();
    }

    public function testInvalidUploadsAndUnknownOrDisabledClients(): void
    {
        $this->grant('client.clients.update');
        $this->browser->request('POST', $this->base.'/logo');
        self::assertResponseStatusCodeSame(400);
        $path = $this->root.'/fake.png';
        file_put_contents($path, '<svg><script>alert(1)</script></svg>');
        $this->browser->request('POST', $this->base.'/logo', [], ['file' => new UploadedFile($path, 'fake.png', 'image/png', null, true)]);
        self::assertResponseStatusCodeSame(400);
        $this->em->find(Client::class, $this->client->getId())->disable();
        $this->em->flush();
        foreach ([$this->base, '/api/clients/'.Uuid::v4(), '/api/clients/invalid'] as $base) {
            foreach (['POST', 'DELETE'] as $method) {
                $this->browser->request($method, $base.'/logo');
                self::assertResponseStatusCodeSame(404);
            }
        }
    }
}
