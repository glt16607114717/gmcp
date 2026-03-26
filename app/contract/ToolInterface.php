<?php

namespace app\contract;

interface ToolInterface
{
    public function getName(): string;
    
    public function getDescription(): string;
    
    public function getInputSchema(): array;
    
    public function execute(array $arguments): array;
}
