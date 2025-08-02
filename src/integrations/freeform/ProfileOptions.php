<?php

namespace craftyfm\filemakerproxy\integrations\freeform;

use craftyfm\filemakerproxy\FmProxy;
use Solspace\Freeform\Attributes\Property\Implementations\Options\OptionCollection;
use Solspace\Freeform\Attributes\Property\Implementations\Options\OptionsGeneratorInterface;
use Solspace\Freeform\Attributes\Property\Property;

class ProfileOptions implements OptionsGeneratorInterface
{

    public function fetchOptions(?Property $property): OptionCollection
    {
        $options = new OptionCollection();
        $profiles = FmProxy::getInstance()->profiles->getEnabledProfiles();

        foreach ($profiles as $profile) {
            $options->add($profile->id, $profile->name);
        }

        return $options;
    }
}