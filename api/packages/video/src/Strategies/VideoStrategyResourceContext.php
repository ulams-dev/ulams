<?php

namespace EscolaLms\Video\Strategies;

class VideoStrategyResourceContext
{
    private VideoResourceStrategy $strategy;

    public function __construct()
    {
        $this->resolve();
    }

    private function resolve(): void
    {
        $this->strategy = new VideoResourceStrategy();

        if (config('escolalms_video.enable')) {
            $this->strategy = new VideoEnableProcessingStrategy();
        }
        if (config('escolalms_video.enable') && config('escolalms_video.non_strict_value')) {
            $this->strategy = new VideoNonStrictValueResourceStrategy();
        }
    }

    public function getStrategy(): VideoResourceStrategy
    {
        return $this->strategy;
    }
}
