<?php

/*
 * This file is part of the broadway/broadway-saga package.
 *
 * (c) Qandidate.com <opensource@qandidate.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require __DIR__ . '/../vendor/autoload.php';

use Broadway\CommandHandling\CommandBus;
use Broadway\Saga\Metadata\StaticallyConfiguredSagaInterface;
use Broadway\Saga\Saga;
use Broadway\Saga\State;
use Broadway\Saga\State\Criteria;
use Broadway\UuidGenerator\UuidGeneratorInterface;

class ReservationSaga extends Saga implements StaticallyConfiguredSagaInterface
{
    private $commandBus;
    private $uuidGenerator;

    public function __construct(
        CommandBus $commandBus,
        UuidGeneratorInterface $uuidGenerator
    ) {
        $this->commandBus    = $commandBus;
        $this->uuidGenerator = $uuidGenerator;
    }

    public static function configuration()
    {
        return [
            'OrderPlaced' => function (OrderPlaced $event) {
                return null; // no criteria, start of a new saga
            },
            'ReservationAccepted' => function (ReservationAccepted $event) {
                // return a Criteria object to fetch the State of this saga
                return new Criteria([
                    'reservationId' => $event->reservationId()
                ]);
            },
            'ReservationRejected' => function (ReservationRejected $event) {
                // return a Criteria object to fetch the State of this saga
                return new Criteria([
                    'reservationId' => $event->reservationId()
                ]);
            }
        ];
    }

    public function handleOrderPlaced(State $state, OrderPlaced $event)
    {
        // keep the order id, for reference in `handleReservationAccepted()` and `handleReservationRejected()`
        $state->set('orderId', $event->orderId());

        // generate an id for the reservation
        $reservationId = $this->uuidGenerator->generate();
        $state->set('reservationId', $reservationId);

        // make the reservation
        $command = new MakeSeatReservation($reservationId, $event->numberOfSeats());
        $this->commandBus->dispatch($command);

        return $state;
    }

    public function handleReservationAccepted(State $state, ReservationAccepted $event)
    {
        // the seat reservation for the given order is has been accepted, mark the order as booked
        $command = new MarkOrderAsBooked($state->get('orderId'));
        $this->commandBus->dispatch($command);

        // the saga ends here
        $state->setDone();

        return $state;
    }

    public function handleReservationRejected(State $state, ReservationRejected $event)
    {
        // the seat reservation for the given order is has been rejected, reject the order as well
        $command = new RejectOrder($state->get('orderId'));
        $this->commandBus->dispatch($command);

        // the saga ends here
        $state->setDone();

        return $state;
    }
}

/**
 * event
 */
class OrderPlaced
{
    private $orderId;
    private $numberOfSeats;

    public function __construct($orderId, $numberOfSeats)
    {
        $this->orderId       = $orderId;
        $this->numberOfSeats = $numberOfSeats;
    }

    public function orderId()
    {
        return $this->orderId;
    }

    public function numberOfSeats()
    {
        return $this->numberOfSeats;
    }
}

/**
 * command
 */
class MakeSeatReservation
{
    private $reservationId;
    private $numberOfSeats;

    public function __construct($reservationId, $numberOfSeats)
    {
        $this->reservationId = $reservationId;
        $this->numberOfSeats = $numberOfSeats;
    }

    public function reservationId()
    {
        return $this->reservationId;
    }

    public function numberOfSeats()
    {
        return $this->numberOfSeats;
    }
}

/**
 * event
 */
class ReservationAccepted
{
    private $reservationId;

    public function __construct($reservationId)
    {
        $this->reservationId = $reservationId;
    }

    public function reservationId()
    {
        return $this->reservationId;
    }
}

/**
 * event
 */
class ReservationRejected
{
    private $reservationId;

    public function __construct($reservationId)
    {
        $this->reservationId = $reservationId;
    }

    public function reservationId()
    {
        return $this->reservationId;
    }
}

/**
 * command
 */
class MarkOrderAsBooked
{
    private $orderId;

    public function __construct($orderId)
    {
        $this->orderId = $orderId;
    }
}

/**
 * command
 */
class RejectOrder
{
    private $orderId;

    public function __construct($orderId)
    {
        $this->orderId = $orderId;
    }
}
