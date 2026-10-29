<?php

namespace Monek\Checkout;

class PluginMetadata
{
    private string $pluginFile;

    public function __construct(string $pluginFile)
    {
        $this->pluginFile = $pluginFile;
    }

    public function getVersion(): string
    {
        $data = get_file_data($this->pluginFile, ['Version' => 'Version']);
        return isset($data['Version']) ? (string) $data['Version'] : '1.0.0';
    }
}
