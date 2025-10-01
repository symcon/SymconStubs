<?php

declare(strict_types=1);

class TileVisualization extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        // This should be in the NotificationControl
        $this->RegisterAttributeInteger('LastNotificationsID', 0);
    }

    public function OpenObject(int $ObjectID, string $TokenList): void
    {
        // No need to implement this function.
    }

    public function PostNotification(string $Title, string $Text, string $Type, int $TargetID): int
    {
        return self::PostNotificationEx($Title, $Text, '', '', $TargetID);
    }

    public function PostNotificationEx(string $Title, string $Text, string $Icon, string $Sound, int $TargetID): int
    {
        $nextNotificationID = $this->ReadAttributeInteger('LastNotificationsID') + 1;
        $this->WriteAttributeInteger('LastNotificationsID', $nextNotificationID);
        return $nextNotificationID;
    }
}
