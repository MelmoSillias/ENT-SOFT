<?php

namespace App\Tests\Integration;

use App\AccessAudit\Domain\Entity\Permission;
use App\AccessAudit\Domain\Entity\UtilisateurPermission;
use App\Client\Domain\Entity\Client;
use App\IdentityAccess\Application\Service\AccessTokenService;
use App\IdentityAccess\Domain\Entity\Utilisateur;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectDocument;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class ProjectDocumentApiTest extends WebTestCase
{
    private KernelBrowser $browser;
    private EntityManagerInterface $em;
    private Utilisateur $user;
    private string $base;
    private string $otherBase;
    private string $projectId;
    private array $payload;

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
        $this->user = new Utilisateur('Test', 'Documents', '12345678', 'test-documents', 'unused', 'TEST_NO_DEFAULTS');
        $client = new Client('CLI-1', 'Client livraison');
        $project = new Project('PRJ-1', 'Projet livraison', $client->getId());
        $other = new Project('PRJ-2', 'Autre projet', $client->getId());
        foreach ([$this->user, $client, $project, $other] as $entity) { $this->em->persist($entity); }
        $this->em->flush();
        $this->projectId = (string) $project->getId();
        $this->base = '/api/projects/'.$project->getId().'/documents';
        $this->otherBase = '/api/projects/'.$other->getId().'/documents';
        $this->payload = ['type' => 'delivery_note', 'number' => 'BC-MANUEL-42', 'date' => '2026-11-20', 'clientId' => (string) $client->getId(), 'lines' => [['description' => 'Routeur', 'quantity' => 2.5]]];
        $token = $container->get(AccessTokenService::class)->create($this->user);
        $this->browser->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);
    }

    private function grant(string ...$codes): void
    {
        foreach ($codes as $code) {
            $permission = new Permission($code, $code, 'project');
            $this->em->persist($permission);
            $this->em->persist(new UtilisateurPermission($this->user->getId(), $permission, true, $this->user->getId()));
        }
        $this->em->flush();
    }

    private function request(string $method, string $uri, ?array $payload = null): array
    {
        $this->browser->request($method, $uri, [], [], ['CONTENT_TYPE' => 'application/json'], $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR));

        return json_decode($this->browser->getResponse()->getContent() ?: '[]', true) ?? [];
    }

    public function testCrudPersistsAndScopesDocumentsToTheirProject(): void
    {
        $this->grant('project.projects.view', 'project.projects.update', 'project.projects.delete', 'impression.documents.print');
        $created = $this->request('POST', $this->base, $this->payload);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(['id', 'projectId', 'type', 'number', 'object', 'date', 'clientId', 'clientTitle', 'lines'], array_keys($created));
        self::assertSame('Client livraison', $created['clientTitle']);
                self::assertNull($created['object']);
                self::assertSame('2026-11-20', $created['date']);
        self::assertSame('RAS', $created['lines'][0]['observation']);
        $id = $created['id'];
        $this->em->clear();
        self::assertSame('BC-MANUEL-42', $this->em->find(ProjectDocument::class, Uuid::fromString($id))->getNumber());
        self::assertCount(1, $this->request('GET', $this->base.'?type=delivery_note'));
        self::assertSame([], $this->request('GET', $this->otherBase));
        foreach (['PUT', 'DELETE'] as $method) {
            $this->request($method, $this->otherBase.'/'.$id, $this->payload);
            self::assertResponseStatusCodeSame(404);
        }
        $this->request('GET', $this->otherBase.'/'.$id.'/print');
        self::assertResponseStatusCodeSame(404);
        $this->request('GET', '/api/impressions/documents/delivery_note/'.$id.'?projectId='.explode('/', $this->otherBase)[3]);
        self::assertResponseStatusCodeSame(404);
        $updated = $this->request('PUT', $this->base.'/'.$id, array_replace($this->payload, ['number' => 'BC-43', 'object' => ' Livraison du matériel ', 'date' => '2026-11-21', 'lines' => [['description' => 'Switch', 'quantity' => 3, 'observation' => 'Livré']]]));
        self::assertResponseIsSuccessful();
        self::assertSame($id, $updated['id']);
                self::assertSame('Livraison du matériel', $updated['object']);
                self::assertSame('2026-11-21', $updated['date']);
        $this->em->clear();
        $persisted = $this->em->find(ProjectDocument::class, Uuid::fromString($id));
                self::assertSame('Livré', $persisted->getLines()[0]['observation']);
                self::assertSame('Livraison du matériel', $persisted->getObject());
                self::assertSame('2026-11-21', $persisted->getDate()->format('Y-m-d'));
                $cleared = $this->request('PUT', $this->base.'/'.$id, $this->payload + ['object' => ' ']);
                self::assertResponseIsSuccessful();
                self::assertNull($cleared['object']);
        $this->request('DELETE', $this->base.'/'.$id);
        self::assertResponseStatusCodeSame(204);
        $this->em->clear();
        self::assertNull($this->em->find(ProjectDocument::class, Uuid::fromString($id)));
        self::assertSame([], $this->request('GET', $this->base));
    }

    public function testValidationReturns400WithoutPersistingAnything(): void
    {
        $this->grant('project.projects.update');
        foreach ([['object' => []], ['object' => str_repeat('x', 501)], ['number' => ' '], ['number' => []], ['clientId' => ''], ['clientId' => (string) Uuid::v4()], ['date' => '2026-02-30'], ['date' => 'tomorrow'], ['type' => 'invoice'], ['lines' => []], ['lines' => [['description' => 'Routeur', 'quantity' => '2']]]] as $change) {
            $this->request('POST', $this->base, array_replace($this->payload, $change));
            self::assertResponseStatusCodeSame(400);
        }
        foreach (['number', 'clientId', 'date', 'type', 'lines'] as $field) {
            $payload = $this->payload;
            unset($payload[$field]);
            $this->request('POST', $this->base, $payload);
            self::assertResponseStatusCodeSame(400);
        }
        self::assertSame(0, $this->em->getRepository(ProjectDocument::class)->count([]));
        $this->request('POST', '/api/projects/'.Uuid::v4().'/documents', $this->payload);
        self::assertResponseStatusCodeSame(404);
    }

    public function testPermissionsAreEnforcedOnCrudAndBothPrintRoutes(): void
    {
        $id = (string) Uuid::v4();
        foreach ([['GET', $this->base], ['POST', $this->base], ['PUT', $this->base.'/'.$id], ['DELETE', $this->base.'/'.$id], ['GET', $this->base.'/'.$id.'/print'], ['GET', '/api/impressions/documents/delivery_note/'.$id.'?projectId='.$this->projectId]] as [$method, $uri]) {
            $this->request($method, $uri, $this->payload);
            self::assertResponseStatusCodeSame(403);
        }
        $this->grant('project.projects.view');
        $this->request('GET', $this->base);
        self::assertResponseIsSuccessful();
        $this->request('GET', $this->base.'/'.$id.'/print');
        self::assertResponseStatusCodeSame(403);
        $this->grant('project.projects.update');
        $created = $this->request('POST', $this->base, $this->payload);
        self::assertResponseStatusCodeSame(201);
        $id = $created['id'];
        $this->request('DELETE', $this->base.'/'.$id);
        self::assertResponseStatusCodeSame(403);
        $this->grant('impression.documents.print');
        foreach ([$this->base.'/'.$id.'/print?template=modern&disposition=attachment', '/api/impressions/documents/delivery_note/'.$id.'?projectId='.$this->projectId.'&template=classic&disposition=attachment'] as $uri) {
            $this->browser->request('GET', $uri);
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('content-type', 'text/html; charset=UTF-8');
            self::assertStringContainsString('BON DE LIVRAISON', $this->browser->getResponse()->getContent());
        }
    }

    public function testPrintParametersAndInactiveClientsAreValidated(): void
    {
        $this->grant('project.projects.view', 'project.projects.update', 'impression.documents.print');
        $created = $this->request('POST', $this->base, $this->payload);
        $id = $created['id'];
        foreach (['format=excel', 'template=unknown', 'page=letter', 'orientation=diagonal', 'disposition=invalid'] as $query) {
            $this->request('GET', $this->base.'/'.$id.'/print?'.$query);
            self::assertResponseStatusCodeSame(400);
        }
        $this->request('GET', '/api/impressions/documents/delivery_note/'.$id);
        self::assertResponseIsSuccessful();
        $this->request('GET', $this->base.'?type=unknown');
        self::assertResponseStatusCodeSame(400);
        $this->request('GET', $this->base.'/'.Uuid::v4().'/print');
        self::assertResponseStatusCodeSame(404);
        $client = $this->em->find(Client::class, Uuid::fromString($this->payload['clientId']));
        $client->disable();
        $this->em->flush();
        $this->request('POST', $this->base, $this->payload);
        self::assertResponseStatusCodeSame(400);
        $this->request('PUT', $this->base.'/'.$id, $this->payload);
        self::assertResponseStatusCodeSame(400);
        $this->request('GET', $this->base);
        self::assertResponseIsSuccessful();
    }

    public function testGlobalCrudSupportsStandaloneDocumentsAndProjectReassignment(): void
    {
        $this->grant('project.projects.view', 'project.projects.update', 'project.projects.delete', 'impression.documents.print');
        $global = '/api/project-documents';
        $standalone = $this->request('POST', $global, $this->payload);
        self::assertResponseStatusCodeSame(201);
        self::assertNull($standalone['projectId']);
        $id = $standalone['id'];
        $this->em->clear();
        self::assertNull($this->em->find(ProjectDocument::class, Uuid::fromString($id))->getProjectId());
        $linked = $this->request('POST', $global, $this->payload + ['projectId' => $this->projectId]);
        self::assertResponseStatusCodeSame(201);
        self::assertSame($this->projectId, $linked['projectId']);
        self::assertCount(2, $this->request('GET', $global.'?type=delivery_note'));
        self::assertCount(1, $this->request('GET', $global.'?projectId='.$this->projectId));
        self::assertCount(1, $this->request('GET', $this->base));
        foreach (['PUT', 'DELETE'] as $method) {
            $this->request($method, $this->base.'/'.$id, $this->payload);
            self::assertResponseStatusCodeSame(404);
        }
        $this->request('GET', $this->base.'/'.$id.'/print');
        self::assertResponseStatusCodeSame(404);
        $this->request('GET', '/api/impressions/documents/delivery_note/'.$id.'?projectId='.$this->projectId);
        self::assertResponseStatusCodeSame(404);
        foreach (['classic', 'modern'] as $template) {
            foreach (['html', 'pdf'] as $format) {
                $this->browser->request('GET', '/api/impressions/documents/delivery_note/'.$id.'?template='.$template.'&format='.$format.'&disposition=attachment');
                self::assertResponseIsSuccessful();
                self::assertResponseHeaderSame('content-type', $format === 'pdf' ? 'application/pdf' : 'text/html; charset=UTF-8');
                self::assertStringContainsString($format === 'pdf' ? '%PDF-' : 'BON DE LIVRAISON', $this->browser->getResponse()->getContent());
            }
        }
        $updated = $this->request('PUT', $global.'/'.$id, $this->payload + ['projectId' => $this->projectId]);
        self::assertResponseIsSuccessful();
        self::assertSame($this->projectId, $updated['projectId']);
        self::assertCount(2, $this->request('GET', $this->base));
        $otherProjectId = explode('/', $this->otherBase)[3];
        $updated = $this->request('PUT', $global.'/'.$id, $this->payload + ['projectId' => $otherProjectId]);
        self::assertResponseIsSuccessful();
        self::assertSame($otherProjectId, $updated['projectId']);
        self::assertCount(1, $this->request('GET', $this->otherBase));
        foreach ([['projectId' => null], []] as $change) {
            $updated = $this->request('PUT', $global.'/'.$id, $this->payload + $change);
            self::assertResponseIsSuccessful();
            self::assertNull($updated['projectId']);
        }
        self::assertCount(1, $this->request('GET', $this->base));
        $this->request('DELETE', $global.'/'.$id);
        self::assertResponseStatusCodeSame(204);
        $this->request('DELETE', $global.'/'.$linked['id']);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([], $this->request('GET', $global));
    }

    public function testGlobalValidationAndNestedProjectCannotBeOverridden(): void
    {
        $this->grant('project.projects.view', 'project.projects.update', 'impression.documents.print');
        foreach (['', 'null', 'invalid', 123, [], false] as $projectId) {
            $this->request('POST', '/api/project-documents', $this->payload + ['projectId' => $projectId]);
            self::assertResponseStatusCodeSame(400);
        }
        $this->request('POST', '/api/project-documents', $this->payload + ['projectId' => (string) Uuid::v4()]);
        self::assertResponseStatusCodeSame(404);
        foreach (['projectId=', 'projectId=null', 'type=unknown'] as $query) {
            $this->request('GET', '/api/project-documents?'.$query);
            self::assertResponseStatusCodeSame(400);
        }
        foreach ([null, explode('/', $this->otherBase)[3]] as $projectId) {
            $this->request('POST', $this->base, $this->payload + ['projectId' => $projectId]);
            self::assertResponseStatusCodeSame(400);
        }
        $nested = $this->request('POST', $this->base, $this->payload + ['projectId' => $this->projectId]);
        self::assertResponseStatusCodeSame(201);
        foreach ([null, explode('/', $this->otherBase)[3]] as $projectId) {
            $this->request('PUT', $this->base.'/'.$nested['id'], $this->payload + ['projectId' => $projectId]);
            self::assertResponseStatusCodeSame(400);
        }
        $this->request('PUT', '/api/project-documents/'.$nested['id'], array_replace($this->payload, ['lines' => []]));
        self::assertResponseStatusCodeSame(400);
        $this->em->clear();
        self::assertSame($this->projectId, $this->em->find(ProjectDocument::class, Uuid::fromString($nested['id']))->getProjectId()->toRfc4122());
        $created = $this->request('POST', '/api/project-documents', $this->payload + ['projectId' => null]);
        self::assertResponseStatusCodeSame(201);
        $this->request('PUT', '/api/project-documents/'.$created['id'], array_replace($this->payload, ['lines' => []]));
        self::assertResponseStatusCodeSame(400);
        $this->request('GET', '/api/impressions/documents/delivery_note/'.$created['id'].'?projectId=');
        self::assertResponseStatusCodeSame(400);
    }

    public function testGlobalPermissionsAndAuthentication(): void
    {
        $id = (string) Uuid::v4();
        $routes = [['GET', '/api/project-documents'], ['POST', '/api/project-documents'], ['PUT', '/api/project-documents/'.$id], ['DELETE', '/api/project-documents/'.$id], ['GET', '/api/impressions/documents/delivery_note/'.$id]];
        foreach ($routes as [$method, $uri]) {
            $this->request($method, $uri, $this->payload);
            self::assertResponseStatusCodeSame(403);
        }
        $this->grant('project.projects.view');
        $this->request('GET', '/api/project-documents');
        self::assertResponseIsSuccessful();
        $this->request('GET', '/api/impressions/documents/delivery_note/'.$id);
        self::assertResponseStatusCodeSame(403);
        $this->browser->setServerParameter('HTTP_AUTHORIZATION', '');
        foreach ($routes as [$method, $uri]) {
            $this->request($method, $uri, $this->payload);
            self::assertResponseStatusCodeSame(401);
        }
    }

    public function testAuthenticationIsRequired(): void
    {
        $this->browser->setServerParameter('HTTP_AUTHORIZATION', '');
        $this->request('GET', $this->base);
        self::assertResponseStatusCodeSame(401);
    }

    public function testDeletedProjectCannotBeUsedForAnyDocumentOperation(): void
    {
        $this->grant('project.projects.view', 'project.projects.update', 'project.projects.delete', 'impression.documents.print');
        $created = $this->request('POST', $this->base, $this->payload);
        $project = $this->em->find(Project::class, Uuid::fromString($this->projectId));
        $project->disable();
        $this->em->flush();
        self::assertSame([], $this->request('GET', '/api/project-documents'));
        foreach ([['GET', '/api/project-documents?projectId='.$this->projectId], ['POST', '/api/project-documents'], ['PUT', '/api/project-documents/'.$created['id']], ['DELETE', '/api/project-documents/'.$created['id']], ['GET', '/api/impressions/documents/delivery_note/'.$created['id']], ['GET', $this->base], ['POST', $this->base], ['PUT', $this->base.'/'.$created['id']], ['DELETE', $this->base.'/'.$created['id']], ['GET', $this->base.'/'.$created['id'].'/print']] as [$method, $uri]) {
            $this->request($method, $uri, $this->payload + ['projectId' => $this->projectId]);
            self::assertResponseStatusCodeSame(404);
        }
    }
}
