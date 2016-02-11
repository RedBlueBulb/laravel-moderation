<?php

use Hootlex\Moderation\ModerationScope;
use Hootlex\Moderation\Status;

use Hootlex\Moderation\Tests\Post;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ModerationScopeTest extends BaseTestCase
{
    use DatabaseTransactions;

    protected $status_column;
    protected $moderated_at_column;
    protected $moderated_by_column;

    public function setUp()
    {
        parent::setUp();

        $this->status_column = 'status';
        $this->moderated_at_column = 'moderated_at';
        $this->moderated_by_column = 'moderated_by';

        Post::$strictModeration = true;
    }

    /** @test */
    public function it_returns_only_approved_stories()
    {
        $this->createPost([$this->status_column => Status::APPROVED], 5);
        $posts = Post::all();
        $this->assertNotEmpty($posts);
        foreach ($posts as $post) {
            $this->assertEquals(Status::APPROVED, $post->{$this->status_column});
        }
    }

    /** @test */
    public function it_returns_only_rejected_stories()
    {
        $this->createPost([$this->status_column => Status::REJECTED], 5);

        $posts = (new Post)->newQueryWithoutScope(new ModerationScope)->rejected()->get();

        $this->assertNotEmpty($posts);

        foreach ($posts as $post) {
            $this->assertEquals(Status::REJECTED, $post->{$this->status_column});
        }
    }

    /** @test */
    public function it_returns_only_pending_stories()
    {
        $this->createPost([$this->status_column => Status::PENDING], 5);

        $posts = (new Post)->newQueryWithoutScope(new ModerationScope)->pending()->get();

        $this->assertNotEmpty($posts);

        foreach ($posts as $post) {
            $this->assertEquals(Status::PENDING, $post->{$this->status_column});
        }
    }

    /** @test */
    public function it_returns_stories_including_pending_ones()
    {
        $this->createPost([$this->status_column => Status::PENDING], 5);

        $posts = (new Post)->newQueryWithoutScope(new ModerationScope)->withPending()->get();

        $this->assertNotEmpty($posts);

        //with pending will return more stories than only approved
        $this->assertTrue($posts > Post::all());

        foreach ($posts as $post) {
            $this->assertTrue(($post->{$this->status_column} == Status::APPROVED || $post->{$this->status_column} == Status::PENDING));
        }
    }

    /** @test */
    public function it_returns_stories_including_rejected_ones()
    {
        $this->createPost([$this->status_column => Status::REJECTED], 5);

        $posts = (new Post)->newQueryWithoutScope(new ModerationScope)->withRejected()->get();

        $this->assertNotEmpty($posts);

        //with rejected will return more stories than only approved
        $this->assertTrue($posts > Post::all());

        foreach ($posts as $post) {
            $this->assertTrue(($post->{$this->status_column} == Status::APPROVED || $post->{$this->status_column} == Status::REJECTED));
        }
    }

    /** @test */
    public function it_returns_all_stories()
    {
        $this->createPost([], 5);
        $posts = (new Post)->newQueryWithoutScope(new ModerationScope)->withAnyStatus()->get();
        $allStories = Post::all()
            ->merge(Post::pending()->get())
            ->merge(Post::rejected()->get());

        $this->assertNotEmpty($posts);

        //with rejected will return more stories than only approved
        $this->assertCount(count($posts), $allStories);
    }

    /** @test */
    public function it_approves_stories()
    {
        $posts = $this->createPost([$this->status_column => Status::PENDING], 4);
        $postsIds = $posts->lists('id')->all();

        (new Post)->newQueryWithoutScope(new ModerationScope)->whereIn('id', $postsIds)->approve();

        foreach ($postsIds as $postId) {
            $this->seeInDatabase('posts', ['id' => $postId, $this->status_column => Status::APPROVED]);
        }
    }

    /** @test */
    public function it_rejects_stories()
    {
        $posts = $this->createPost([$this->status_column => Status::PENDING], 4);
        $postsIds = $posts->lists('id')->all();

        (new Post)->newQueryWithoutScope(new ModerationScope)->whereIn('id', $postsIds)->reject();

        foreach ($postsIds as $postId) {
            $this->seeInDatabase('posts', ['id' => $postId, $this->status_column => Status::REJECTED]);
        }
    }

    /** @test */
    public function it_approves_a_story_by_id()
    {
        $post = $this->createPost([$this->status_column => Status::PENDING]);

        (new Post)->newQueryWithoutScope(new ModerationScope)->approve($post->id);

        $this->seeInDatabase('posts',
            [
                'id' => $post->id,
                $this->status_column => Status::APPROVED,
                $this->moderated_at_column => \Carbon\Carbon::now(),
                $this->moderated_by_column => \Auth::user()
            ]);
    }

    /** @test */
    public function it_rejects_a_story_by_id()
    {
        $post = $this->createPost([$this->status_column => Status::PENDING]);

        (new Post)->newQueryWithoutScope(new ModerationScope)->reject($post->id);

        $this->seeInDatabase('posts',
            [
                'id' => $post->id,
                $this->status_column => Status::REJECTED,
                $this->moderated_at_column => \Carbon\Carbon::now(),
                $this->moderated_by_column => \Auth::user()
            ]);
    }

    /** @test */
    public function it_returns_approved_and_pending_stories_when_not_in_strict_mode()
    {
        Post::$strictModeration = false;

        $this->createPost([$this->status_column => Status::PENDING], 4);
        $this->createPost([$this->status_column => Status::APPROVED], 2);

        $posts = Post::all();

        $pendingCount = count(Post::pending()->get());
        $this->assertTrue($posts->count() > $pendingCount);

        $this->assertNotEmpty($posts);

        foreach ($posts as $post) {
            $this->assertTrue(($post->{$this->status_column} == Status::APPROVED || $post->{$this->status_column} == Status::PENDING));
        }
    }

    /** @test */
    public function it_queries_pending_stories_by_default_when_not_in_strict_mode()
    {
        Post::$strictModeration = false;

        $posts = $this->createPost([$this->status_column => Status::PENDING], 5);
        $postsIds = $posts->lists('id')->all();

        $postsReturned = Post::whereIn('id', $postsIds)->get();

        $this->assertCount(5, $postsReturned);

        foreach ($posts as $post) {
            $this->assertTrue(($post->{$this->status_column} == Status::PENDING));
        }
    }
}
