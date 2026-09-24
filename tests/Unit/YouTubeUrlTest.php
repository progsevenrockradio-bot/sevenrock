<?php

namespace Tests\Unit;

use App\Support\YouTubeUrl;
use PHPUnit\Framework\TestCase;

class YouTubeUrlTest extends TestCase
{
    public function test_it_extracts_video_id_from_standard_url()
    {
        $this->assertEquals('oV16Qipd0MQ', YouTubeUrl::videoId('https://www.youtube.com/watch?v=oV16Qipd0MQ'));
        $this->assertEquals('oV16Qipd0MQ', YouTubeUrl::videoId('youtube.com/watch?v=oV16Qipd0MQ&feature=share'));
    }

    public function test_it_extracts_video_id_from_short_url()
    {
        $this->assertEquals('oV16Qipd0MQ', YouTubeUrl::videoId('https://youtu.be/oV16Qipd0MQ'));
        $this->assertEquals('oV16Qipd0MQ', YouTubeUrl::videoId('youtu.be/oV16Qipd0MQ?t=12'));
    }

    public function test_it_extracts_video_id_from_shorts_and_embed()
    {
        $this->assertEquals('oV16Qipd0MQ', YouTubeUrl::videoId('https://www.youtube.com/shorts/oV16Qipd0MQ'));
        $this->assertEquals('oV16Qipd0MQ', YouTubeUrl::videoId('https://www.youtube.com/embed/oV16Qipd0MQ'));
        $this->assertEquals('oV16Qipd0MQ', YouTubeUrl::videoId('https://www.youtube.com/live/oV16Qipd0MQ'));
    }

    public function test_it_rejects_channels_and_playlists()
    {
        $this->assertNull(YouTubeUrl::videoId('https://youtube.com/@Bottlenext'));
        $this->assertNull(YouTubeUrl::videoId('https://www.youtube.com/channel/UC_x5XG1OV2P6uZZ5FSM9Ttw'));
        $this->assertNull(YouTubeUrl::videoId('https://www.youtube.com/user/pewdiepie'));
        $this->assertNull(YouTubeUrl::videoId('https://www.youtube.com/playlist?list=PL_foo_bar'));
        $this->assertNull(YouTubeUrl::videoId('https://www.youtube.com/c/Creator'));
    }

    public function test_it_rejects_invalid_or_empty_urls()
    {
        $this->assertNull(YouTubeUrl::videoId('https://vimeo.com/12345678'));
        $this->assertNull(YouTubeUrl::videoId(''));
        $this->assertNull(YouTubeUrl::videoId(null));
        $this->assertNull(YouTubeUrl::videoId('not a url'));
    }
}
