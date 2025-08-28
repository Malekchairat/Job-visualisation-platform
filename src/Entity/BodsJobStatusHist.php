<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'BODS_JOB_STATUS_HIST')]
class BodsJobStatusHist
{
    // Mark jobName as part of the composite primary key
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 50)]
    private ?string $jobName = null;

    // Mark runNbr as part of the composite primary key
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private ?int $runNbr = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $currentDate = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: 'string', length: 2)]
    private ?string $status = null;

    // --- GETTERS AND SETTERS ---

    public function getJobName(): ?string
    {
        return $this->jobName;
    }

    public function getRunNbr(): ?int
    {
        return $this->runNbr;
    }

    public function getCurrentDate(): ?\DateTimeInterface
    {
        return $this->currentDate;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    // You might need setters if you create these entities in code
    // For now, we only need getters to display the data.

    /**
     * Custom method to calculate duration for the Twig template.
     */
    public function getDuration(): string
    {
        if ($this->startDate && $this->endDate) {
            $interval = $this->startDate->diff($this->endDate);
            return $interval->format('%Hh %Im %Ss');
        }
        return 'N/A';
    }

    /**
     * Custom method to provide status info for the Twig template.
     */
    public function getStatusInfo(): array
    {
        return match ($this->status) {
            'OK' => ['name' => 'Completed', 'class' => 'status-ok'],
            'ER' => ['name' => 'Error', 'class' => 'status-er'],
            'IP' => ['name' => 'In Progress', 'class' => 'status-ip'],
            'PE' => ['name' => 'Pending', 'class' => 'status-pe'],
            default => ['name' => 'Unknown', 'class' => 'status-unknown'],
        };
    }
}
