<?php

namespace App\Form;

use App\Entity\Address;
use App\Entity\Order;
use App\Entity\ProductVariant;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminOrderFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => function (User $user) {
                    return sprintf('%s %s (%s)', $user->getFirstName(), $user->getLastName(), $user->getEmail());
                },
                'label' => 'Customer',
                'placeholder' => 'Select customer...',
                'required' => true,
            ])
            ->add('shippingAddress', EntityType::class, [
                'class' => Address::class,
                'choice_label' => function (Address $addr) {
                    return sprintf('%s, %s, %s', $addr->getCity(), $addr->getProvince(), $addr->getCountry());
                },
                'label' => 'Shipping Address',
                'placeholder' => 'Select address...',
                'required' => true,
            ])
            ->add('billingAddress', EntityType::class, [
                'class' => Address::class,
                'choice_label' => function (Address $addr) {
                    return sprintf('%s, %s, %s', $addr->getCity(), $addr->getProvince(), $addr->getCountry());
                },
                'label' => 'Billing Address (optional)',
                'required' => false,
            ])
            ->add('subtotal', MoneyType::class, [
                'currency' => 'PHP',
                'label' => 'Subtotal',
                'required' => false,
                'mapped' => false,
            ])
            ->add('discount', MoneyType::class, [
                'currency' => 'PHP',
                'label' => 'Discount',
                'required' => false,
            ])
            ->add('tax', MoneyType::class, [
                'currency' => 'PHP',
                'label' => 'Tax',
                'required' => false,
            ])
            ->add('shippingCost', MoneyType::class, [
                'currency' => 'PHP',
                'label' => 'Shipping Cost',
                'required' => false,
            ])
            ->add('status', HiddenType::class, [
                'data' => Order::STATUS_PENDING,
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Customer Notes',
                'required' => false,
            ])
            ->add('internalNotes', TextareaType::class, [
                'label' => 'Internal Notes',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
        ]);
    }
}
