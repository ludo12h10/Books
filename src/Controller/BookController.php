<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BookController extends AbstractController
{
    #[Route('/api/books', name: 'book', methods: ['GET'])]
    public function getBookList(
        BookRepository $bookRepository
    ): JsonResponse
    {
        $bookList = $bookRepository->findAll();
        return $this->json([
            'books' => $bookList,
        ], 200, [], ["groups" => "getBooks"]);
    }

    #[Route('/api/books/{id}', name: 'detailBook', requirements: ['id' => '\d+'], methods: ['GET'],)]
    public function getDetailBook(
        Book           $book,
        BookRepository $bookRepository,
    ): JsonResponse
    {
        return $this->json([
            'books' => $book,
        ], 200, [], ['groups' => 'getBooks']);
    }

    #[Route('/api/books/{id}', name: 'deleteBook', methods: ['DELETE'])]
    public function deleteBook(
        Book                   $book,
        EntityManagerInterface $em): JsonResponse
    {
        $em->remove($book);
        $em->flush();

        return $this->json(null, 204);
    }

    #[Route('/api/books', name: "createBook", methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas les droits suffisant pour créer un livre")]
    public function createBook(
        EntityManagerInterface $entityManager,
        AuthorRepository       $authorRepository,
        SerializerInterface    $serializer,
        Request                $request,
        UrlGeneratorInterface  $urlGenerator,
        ValidatorInterface     $validator
    ): JsonResponse
    {
        $book = $serializer->deserialize($request->getContent(), Book::class, 'json');
        $errors = $validator->validate($book);
        if ($errors->count() > 0)
        {
            return $this->json($errors, 400);
        }
        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;
        $book->setAuthor($authorRepository->find($idAuthor));

        $entityManager->persist($book);
        $entityManager->flush();

        $location = $urlGenerator->generate(
            'detailBook',
            ['id' => $book->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->json($book, 201, ['location' => $location], ['groups' => 'getBooks']);
    }

    #[Route('/api/books/{id}', name: "updateBook", methods: ['PUT'])]
    public function updateBook(
        SerializerInterface    $serializer,
        Request                $request,
        Book                   $currentBook,
        EntityManagerInterface $entityManager,
        AuthorRepository       $authorRepository,
    ): JsonResponse
    {
        $updatedBook = $serializer->deserialize(
            $request->getContent(),
            Book::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentBook]);
        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;
        $updatedBook->setAuthor($authorRepository->find($idAuthor));

        $entityManager->persist($updatedBook);
        $entityManager->flush();

        return $this->json(null, 204);
    }
}
