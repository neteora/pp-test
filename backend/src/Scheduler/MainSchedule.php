<?php

namespace App\Scheduler;

use App\Message\CheckDuePosts;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/** Transport: scheduler_default. Il lock su MySQL evita tick doppi con piu' istanze del worker. */
#[AsSchedule('default')]
final class MainSchedule implements ScheduleProviderInterface
{
    public function __construct(private LockFactory $lockFactory) {}

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(RecurringMessage::every('1 minute', new CheckDuePosts()))
            ->lock($this->lockFactory->createLock('postpickr-schedule'));
    }
}
