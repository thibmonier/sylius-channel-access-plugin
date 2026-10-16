<?php

declare(strict_types=1);

namespace Tmonier\SyliusChannelAccessPlugin\Form\Extension;

use Sylius\Bundle\ChannelBundle\Form\Type\ChannelType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessMode;
use Tmonier\SyliusChannelAccessPlugin\Entity\Channel\AccessModeAwareInterface;

final class ChannelTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [ChannelType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $dataClass = $options['data_class'] ?? null;
        if (!\is_string($dataClass) || !is_a($dataClass, AccessModeAwareInterface::class, true)) {
            return;
        }

        $builder->add('accessMode', EnumType::class, [
            'class' => AccessMode::class,
            'label' => 'tmonier_channel_access.form.access_mode',
            'help' => 'tmonier_channel_access.form.access_mode_help',
            'choice_label' => static fn (AccessMode $mode): string => 'tmonier_channel_access.mode.' . $mode->value,
            'invalid_message' => 'tmonier_channel_access.access_mode.invalid',
            'required' => true,
        ]);
    }
}
