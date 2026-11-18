<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;

class BumpVersion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'version:bump {type=patch : The type of version bump (major, minor, patch, build)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Increment application version and build number';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = base_path('version.json');
        
        if (!file_exists($filePath)) {
            $data = [
                'version' => '1.0.0',
                'build' => 1,
                'last_updated' => Carbon::now()->toDateTimeString()
            ];
        } else {
            $data = json_decode(file_get_contents($filePath), true) ?? [
                'version' => '1.0.0',
                'build' => 1,
                'last_updated' => Carbon::now()->toDateTimeString()
            ];
        }

        $type = $this->argument('type');
        $versionParts = explode('.', $data['version']);
        if (count($versionParts) < 3) {
            $versionParts = [1, 0, 0];
        }

        $major = (int)$versionParts[0];
        $minor = (int)$versionParts[1];
        $patch = (int)$versionParts[2];
        $build = (int)($data['build'] ?? 1);

        switch ($type) {
            case 'major':
                $major++;
                $minor = 0;
                $patch = 0;
                break;
            case 'minor':
                $minor++;
                $patch = 0;
                break;
            case 'patch':
                $patch++;
                break;
            case 'build':
                // Only incrementing build, version stays the same
                break;
            default:
                $this->error("Invalid bump type! Use 'major', 'minor', 'patch', or 'build'.");
                return Command::FAILURE;
        }

        $build++;
        $newVersion = "{$major}.{$minor}.{$patch}";
        
        $data['version'] = $newVersion;
        $data['build'] = $build;
        $data['last_updated'] = Carbon::now()->toDateTimeString();

        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));

        $this->info("Version updated to: v{$newVersion} (Build {$build})");
        return Command::SUCCESS;
    }
}
