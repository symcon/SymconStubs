<?php

declare(strict_types=1);

class ConnectControl extends IPSModule
{
    public function ActivateServer()
    {
        throw new Exception("'ActivateServer' is not yet implemented");
    }

    public function GetConnectURL()
    {
        throw new Exception("'GetConnectURL' is not yet implemented");
    }

    public function GetQRCodeSVG(int $WebFrontVisualizationID)
    {
        throw new Exception("'GetQRCodeSVG' is not yet implemented");
    }

    public function MakeRequest(string $Endpoint, string $RequestData)
    {
        throw new Exception("'MakeRequest' is not yet implemented");
    }

    public function SendGoogleAssistantStateReport(string $States)
    {
        throw new Exception("'SendGoogleAssistantStateReport' is not yet implemented");
    }

    public function GetRequestLimitCount()
    {
        throw new Exception("'GetRequestLimitCount' is not yet implemented");
    }

    public function GetTrafficStatistics()
    {
        throw new Exception("'GetTrafficStatistics' is not yet implemented");
    }

    public function GetGoogleAssistantLimitCount()
    {
        throw new Exception("'GetGoogleAssistantLimitCount' is not yet implemented");
    }
}
