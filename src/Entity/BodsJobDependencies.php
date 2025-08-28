<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'BODS_JOB_DEPENDENCIES')]
class BodsJobDependencies
{
    #[ORM\Id]
    #[ORM\Column(name: 'JOB_NAME', type: 'string', length: 50)]
    private string $jobName;

    #[ORM\Id]
    #[ORM\Column(name: 'DEPEND_ON', type: 'string', length: 50)]
    private string $dependOn;

    // Getters and Setters
    public function getJobName(): string
    {
        return $this->jobName;
    }

    public function setJobName(string $jobName): self
    {
        $this->jobName = $jobName;
        return $this;
    }

    public function getDependOn(): string
    {
        return $this->dependOn;
    }

    public function setDependOn(string $dependOn): self
    {
        $this->dependOn = $dependOn;
        return $this;
    }
}