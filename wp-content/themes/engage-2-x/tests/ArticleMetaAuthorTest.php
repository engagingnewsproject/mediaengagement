<?php

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

// Run with --no-configuration --bootstrap vendor/autoload.php.
class ArticleMetaAuthorTest extends TestCase
{
    public function testAuthorLinksUseOnlyUnambiguousPublishedProfiles(): void
    {
        foreach ([[], [(object) ['ID' => 42]], [(object) ['ID' => 42], (object) ['ID' => 43]]] as $profiles) {
            $html = $this->renderMeta('announcement', $profiles);
            $this->assertStringContainsString('Jane Doe', $html);
            $this->assertStringNotContainsString('/team/jane-doe', $html);
            if (count($profiles) === 1) {
                $this->assertStringContainsString('href="https://example.org/team/jane-research-profile/"', $html);
            } else {
                $this->assertStringNotContainsString('author__link', $html);
            }
        }
    }

    public function testResearchAndBlogsKeepTheirDedicatedMetadata(): void
    {
        foreach (['research', 'blogs'] as $type) {
            $html = $this->renderMeta($type, []);
            $this->assertStringContainsString('dedicated-research-metadata', $html);
            $this->assertStringNotContainsString('author__by', $html);
            $this->assertStringNotContainsString('testArticleMetaTwig', $html);
            if ($type === 'research') {
                $this->assertStringContainsString('article__category--journalism', $html);
            }
        }
    }

    private function renderMeta($type, $profiles): string
    {
        $post = new class {
            public $post_type;
            public $author;
            public $date = 'November 16, 2026';
            public function terms($taxonomy) { return [(object) ['slug' => 'journalism', 'name' => 'Journalism']]; }
            public function meta($key) { return null; }
        };
        $post->post_type = $type;
        $post->author = (object) ['name' => 'Jane Doe'];
        $twig = new Environment(new ChainLoader([
            new ArrayLoader([
                'partial/article-meta-data.twig' => '',
                'partial/research-meta.twig' => 'dedicated-research-metadata',
                'partial/social.twig' => '',
            ]),
            new FilesystemLoader(dirname(__DIR__) . '/templates'),
        ]));
        $twig->addFunction(new TwigFunction('function', function ($name, ...$args) use ($profiles, $type) {
            $this->assertNotContains($type, ['research', 'blogs']);
            if ($name === 'get_posts') {
                $this->assertSame(['post_type' => 'team', 'post_status' => 'publish', 'title' => 'Jane Doe', 'posts_per_page' => 2], $args[0]);
                return $profiles;
            }
            $this->assertSame('get_permalink', $name);
            $this->assertSame(42, $args[0]);
            return 'https://example.org/team/jane-research-profile/';
        }));
        return $twig->render('partial/article-meta.twig', ['post' => $post]);
    }
}
