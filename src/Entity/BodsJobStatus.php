<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'BODS_JOB_STATUS')]
#[ORM\UniqueConstraint(name: 'PK_BODS_JOB_STATUS_RUN', columns: ['run_nbr'])]
class BodsJobStatus
{
    #[ORM\Id]
    #[ORM\Column(name: 'JOB_NAME', type: 'string', length: 50)]
    private string $jobName;

    #[ORM\Column(name: 'RUN_NBR', type: 'integer')]
    private int $runNbr;

    #[ORM\Column(name: 'CURRENT_DATE', type: 'datetime')]
    private \DateTime $currentDate;

    #[ORM\Column(name: 'START_DATE', type: 'datetime')]
    private \DateTime $startDate;

    #[ORM\Column(name: 'END_DATE', type: 'datetime', nullable: true)]
    private ?\DateTime $endDate = null;

    #[ORM\Column(name: 'STATUS', type: 'string', length: 2)]
    private string $status;

    #[ORM\Column(name: 'STARTED', type: 'boolean', options: ['default' => 0])]
    private bool $started = false;

    #[ORM\Column(name: 'FLAG', type: 'boolean', nullable: true)]
    private ?bool $flag = null;

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

    public function getRunNbr(): int
    {
        return $this->runNbr;
    }

    public function setRunNbr(int $runNbr): self
    {
        $this->runNbr = $runNbr;
        return $this;
    }

    public function getCurrentDate(): \DateTime
    {
        return $this->currentDate;
    }

    public function setCurrentDate(\DateTime $currentDate): self
    {
        $this->currentDate = $currentDate;
        return $this;
    }

    public function getStartDate(): \DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTime $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTime $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function setStarted(bool $started): self
    {
        $this->started = $started;
        return $this;
    }

    public function getFlag(): ?bool
    {
        return $this->flag;
    }

    public function setFlag(?bool $flag): self
    {
        $this->flag = $flag;
        return $this;
    }

    /**
     * Get status display name and color class
     */
    public function getStatusInfo(): array
    {
        return match($this->status) {
            'IP' => ['name' => 'In Progress', 'class' => 'status-in-progress', 'color' => 'blue'],
            'OK' => ['name' => 'Completed', 'class' => 'status-completed', 'color' => 'green'],
            'ER' => ['name' => 'Error', 'class' => 'status-error', 'color' => 'red'],
            'PE' => ['name' => 'Pending', 'class' => 'status-pending', 'color' => 'white'],
            'KO' => ['name' => 'Killed', 'class' => 'status-killed', 'color' => 'purple'],
            'EC' => ['name' => 'Execution Canceled', 'class' => 'status-canceled', 'color' => 'orange'],
            default => ['name' => 'Unknown', 'class' => 'status-unknown', 'color' => 'gray']
        };
    }

    /**
     * Check if job is currently running
     */
    public function isRunning(): bool
    {
        return $this->status === 'IP' && $this->started;
    }

    /**
     * Check if job is completed successfully
     */
    public function isCompleted(): bool
    {
        return $this->status === 'OK' && $this->endDate !== null;
    }

    /**
     * Check if job has error
     */
    public function hasError(): bool
    {
        return $this->status === 'ER';
    }

    /**
     * Get duration if job is completed
     */
    public function getDuration(): ?string
    {
        if ($this->endDate === null) {
            return null;
        }

        $interval = $this->startDate->diff($this->endDate);
        return $interval->format('%H:%I:%S');
    }
}
