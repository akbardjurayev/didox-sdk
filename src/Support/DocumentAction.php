<?php

declare(strict_types=1);

namespace Didox\Support;

enum DocumentAction: string
{
    case ACCEPT = 'accept';
    case CANCEL = 'cancel';
    case REJECT = 'reject';
    case RESPONSIBLE_GIVE = 'responsibleGive';
    case RESPONSIBLE_ACCEPT = 'responsibleAccept';
    case RESPONSIBLE_TILL_RETURN = 'responsibleTillReturn';
    case RESPONSIBLE_RETURN = 'responsibleReturn';
    case CONSIGNOR_RETURN = 'consignorReturn';
    case CONSIGNOR_RETURN_ACCEPT = 'consignorReturnAccept';
    case ACCOUNTANT_ACCEPT = 'accountantAccept';
    case AGENT_ACCEPT = 'agentAccept';
}
