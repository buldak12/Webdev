<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\AgeVerification;
use App\Entity\User;
use App\Form\AddressType;
use App\Repository\AddressRepository;
use App\Repository\AgeVerificationRepository;
use App\Repository\CategoryRepository;
use App\Repository\OrderRepository;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/account')]
#[IsGranted('ROLE_USER')]
class AccountController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CategoryRepository $categoryRepository,
        private OrderRepository $orderRepository,
        private AddressRepository $addressRepository,
        private AgeVerificationRepository $ageVerificationRepository,
        private CartService $cartService
    ) {}

    #[Route('', name: 'account_index')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $recentOrders = $this->orderRepository->findByUser($user, 5);
        $cart = $this->cartService->getCart($user);

        return $this->render('account/index.html.twig', [
            'user' => $user,
            'recent_orders' => $recentOrders,
            'categories_menu' => $this->categoryRepository->findActive(),
            'cart_count' => $cart->getItemCount(),
        ]);
    }

    #[Route('/orders', name: 'account_orders')]
    public function orders(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $orders = $this->orderRepository->findByUser($user);
        $cart = $this->cartService->getCart($user);

        return $this->render('account/orders.html.twig', [
            'orders' => $orders,
            'categories_menu' => $this->categoryRepository->findActive(),
            'cart_count' => $cart->getItemCount(),
        ]);
    }

    #[Route('/orders/{id}', name: 'account_order_show')]
    public function orderShow(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $order = $this->orderRepository->find($id);
        
        if (!$order || $order->getUser() !== $user) {
            throw $this->createNotFoundException('Order not found');
        }
        
        $cart = $this->cartService->getCart($user);

        return $this->render('account/order_show.html.twig', [
            'order' => $order,
            'categories_menu' => $this->categoryRepository->findActive(),
            'cart_count' => $cart->getItemCount(),
        ]);
    }

    #[Route('/addresses', name: 'account_addresses')]
    public function addresses(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $addresses = $this->addressRepository->findBy(['user' => $user], ['isDefaultShipping' => 'DESC', 'createdAt' => 'DESC']);
        $cart = $this->cartService->getCart($user);
        
        // New address form
        $newAddress = new Address();
        $newAddress->setUser($user);
        $newAddress->setFullName($user->getFullName());
        $addressForm = $this->createForm(AddressType::class, $newAddress);
        $addressForm->handleRequest($request);
        
        if ($addressForm->isSubmitted() && $addressForm->isValid()) {
            if (count($addresses) === 0) {
                $newAddress->setIsDefaultShipping(true);
                $newAddress->setIsDefaultBilling(true);
            }
            $this->entityManager->persist($newAddress);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Address added successfully');
            return $this->redirectToRoute('account_addresses');
        }

        return $this->render('account/addresses.html.twig', [
            'addresses' => $addresses,
            'address_form' => $addressForm->createView(),
            'categories_menu' => $this->categoryRepository->findActive(),
            'cart_count' => $cart->getItemCount(),
        ]);
    }

    #[Route('/addresses/{id}/delete', name: 'account_address_delete', methods: ['POST'])]
    public function deleteAddress(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $address = $this->addressRepository->find($id);
        
        if ($address && $address->getUser() === $user) {
            $this->entityManager->remove($address);
            $this->entityManager->flush();
            $this->addFlash('success', 'Address deleted');
        }
        
        return $this->redirectToRoute('account_addresses');
    }

    #[Route('/addresses/{id}/default', name: 'account_address_default', methods: ['POST'])]
    public function setDefaultAddress(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $address = $this->addressRepository->find($id);
        
        if ($address && $address->getUser() === $user) {
            // Remove default from all addresses
            foreach ($this->addressRepository->findBy(['user' => $user]) as $addr) {
                $addr->setIsDefaultShipping(false);
            }
            $address->setIsDefaultShipping(true);
            $this->entityManager->flush();
            $this->addFlash('success', 'Default address updated');
        }
        
        return $this->redirectToRoute('account_addresses');
    }

    #[Route('/profile', name: 'account_profile')]
    public function profile(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $cart = $this->cartService->getCart($user);
        
        if ($request->isMethod('POST')) {
            $user->setFirstName($request->request->get('first_name'));
            $user->setLastName($request->request->get('last_name'));
            $user->setPhone($request->request->get('phone'));
            
            $newPassword = $request->request->get('new_password');
            if ($newPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            }
            
            $this->entityManager->flush();
            $this->addFlash('success', 'Profile updated successfully');
            return $this->redirectToRoute('account_profile');
        }

        return $this->render('account/profile.html.twig', [
            'user' => $user,
            'categories_menu' => $this->categoryRepository->findActive(),
            'cart_count' => $cart->getItemCount(),
        ]);
    }

    #[Route('/verify-age', name: 'account_verify_age')]
    public function verifyAge(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $cart = $this->cartService->getCart($user);
        
        // Check existing verification
        $verification = $this->ageVerificationRepository->findOneBy(['user' => $user]);
        
        if ($user->isAgeVerified()) {
            $this->addFlash('info', 'Your age has already been verified');
            return $this->redirectToRoute('account_index');
        }
        
        if ($verification && $verification->getStatus() === AgeVerification::STATUS_PENDING) {
            $this->addFlash('info', 'Your age verification is pending review');
            return $this->render('account/verify_age_pending.html.twig', [
                'verification' => $verification,
                'categories_menu' => $this->categoryRepository->findActive(),
                'cart_count' => $cart->getItemCount(),
            ]);
        }
        
        if ($request->isMethod('POST')) {
            // For demo: auto-approve verification
            $idType = (string) $request->request->get('document_type', AgeVerification::ID_TYPE_DRIVERS_LICENSE);
            if (!array_key_exists($idType, AgeVerification::ID_TYPES)) {
                $idType = AgeVerification::ID_TYPE_DRIVERS_LICENSE;
            }

            $dateOfBirth = $user->getBirthDate() ?? new \DateTimeImmutable('-21 years');

            $verification = new AgeVerification();
            $verification->setUser($user);
            $verification->setIdType($idType);
            $verification->setIdFrontImage('uploads/verification/placeholder.jpg');
            $verification->setDateOfBirth($dateOfBirth);
            $verification->setStatus(AgeVerification::STATUS_APPROVED);
            $verification->setReviewedAt(new \DateTime());
            
            $user->setAgeVerificationStatus(User::AGE_STATUS_VERIFIED);
            $user->setAgeVerifiedAt(new \DateTime());
            if ($user->getBirthDate() === null) {
                $user->setBirthDate($dateOfBirth);
            }
            
            $this->entityManager->persist($verification);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Age verification approved! You can now purchase age-restricted products.');
            return $this->redirectToRoute('account_index');
        }

        return $this->render('account/verify_age.html.twig', [
            'categories_menu' => $this->categoryRepository->findActive(),
            'cart_count' => $cart->getItemCount(),
        ]);
    }
}
