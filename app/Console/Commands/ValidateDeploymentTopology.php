<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Operations\Services\DeploymentTopologyValidator;
use Illuminate\Console\Command;

final class ValidateDeploymentTopology extends Command
{
    protected $signature = 'operations:validate-deployment';

    protected $description = 'Validate the configured provider-neutral deployment topology without network access.';

    public function handle(DeploymentTopologyValidator $validator): int
    {
        $result = $validator->validate();

        if ($result->passes()) {
            $this->components->info('deployment-topology: valid');

            return self::SUCCESS;
        }

        $this->components->error('deployment-topology: invalid');
        foreach ($result->issueCodes() as $issue) {
            $this->line("issue: {$issue}");
        }

        return self::FAILURE;
    }
}
