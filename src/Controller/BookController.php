<?php

namespace App\Controller;

use App\Entity\Book;
use OpenApi\Attributes as OA;
use App\Repository\BookRepository;
use App\Service\VersioningService;
use App\Repository\AuthorRepository;
use JMS\Serializer\SerializerInterface;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Contracts\Cache\ItemInterface;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class BookController extends AbstractController
{

    /**
     * Cette méthode permet de récupérer l'ensemble des livres.
     *
   
     * 
     *
     * @param BookRepository $bookRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     * 
     */
    #[Route('/api/books', name: 'books', methods: ['GET'])]
    #[OA\Tag(name: 'Books')]
    #[OA\Response(
       response:200,
       description: "Retourne la liste des livres",
       content: new OA\JsonContent(
        type:"array",
        items: new OA\Items(ref: new Model(type: Book::class, groups: ['getBooks']))
       ) 
    )]
    #[OA\Parameter(
        name: "page",
        in: 'query',
        description: "La page que l'on veut récupérer",
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: "limit",
        in: 'query',
        description: "Le nombre d'éléments que l'on veut récupérer",
        schema: new OA\Schema(type: 'string')
    )]    
    
    public function getAllBooks(
        BookRepository $bookRepository,
        SerializerInterface $serializer,
        Request $request,
        TagAwareCacheInterface $cachePool
        ): JsonResponse
    {
        $page=$request->get('page',1);
        $limit=$request->get('limit',3);

        $idCache="getAllBooks"."-".$page."-".$limit;

        $jsonBookList=$cachePool->get($idCache,function(ItemInterface $item) use($bookRepository,$page,$limit,$serializer) {
                echo'CET ELEMENT N\'EST PAS ENCORE DANS LE CAHCE';
                $item->tag('booksCahe');
                //$item->expiresAfter(60);
                $context=SerializationContext::create()->setGroups('getBooks');
                $booklist= $bookRepository->findAllWithPagination($page,$limit);
                return $serializer->serialize($booklist,'json',$context);
            });

        return new JsonResponse($jsonBookList,Response::HTTP_OK,[],true);
    }

    #[Route('/api/books/{id}', name: 'detailBook', methods: ['GET'])]
    public function getDetailBook(Book $book, 
        SerializerInterface $serializer, 
        BookRepository $bookRepository,
        VersioningService $versioningService
        ): JsonResponse 
    {
        $context=SerializationContext::create()->setGroups('getBooks');
        $context->setVersion($versioningService->getVersion());
        $jsonBook = $serializer->serialize($book, 'json',$context);
        return new JsonResponse($jsonBook, Response::HTTP_OK, ['accept'=>'json'], true);
    }

    #[Route('/api/books/{id}', name: 'deleteBook', methods: ['DELETE'])]
    public function deleteBook(Book $book, EntityManagerInterface $em,TagAwareCacheInterface $cachePool): JsonResponse {

        $cachePool->invalidateTags(['booksCache']);
        $em->remove($book);
        $em->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/books', name: 'createBook', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN',message:"Vous n'avez pas les droits suffisants pour créer un livre")]
    public function createBook(
        Request $request, 
        EntityManagerInterface $em,
        SerializerInterface $serializer,
        UrlGeneratorInterface $urlGenerator,
        AuthorRepository $authorRepository,
        ValidatorInterface $validator
        ): JsonResponse {

            $book=$serializer->deserialize($request->getContent(),Book::class,'json');

            //on vérifie les erreurs
            $errors=$validator->validate($book);

            if ($errors->count() >0) {
               return new JsonResponse($serializer->serialize($errors,'json'),Response::HTTP_BAD_REQUEST,[],true);
            //    throw new HttpException(JsonResponse::HTTP_BAD_REQUEST,"La requête est invalide");
            }

            // Récupération de l'ensemble des données envoyées sous forme de tableau
            $content = $request->toArray();

            // Récupération de l'idAuthor. S'il n'est pas défini, alors on met -1 par défaut.
            $idAuthor = $content['idAuthor'] ?? -1;

            // On cherche l'auteur qui correspond et on l'assigne au livre.
            // Si "find" ne trouve pas l'auteur, alors null sera retourné.
            $book->setAuthor($authorRepository->find($idAuthor));

            $em->persist($book);
            $em->flush();

            $context=SerializationContext::create()->setGroups('getBooks');
            $jsonBook = $serializer->serialize($book, 'json',$context);
            $location= $urlGenerator->generate('detailBook',['id'=>$book->getId()],UrlGeneratorInterface::ABSOLUTE_URL);
            return new JsonResponse( $jsonBook, Response::HTTP_CREATED,['Location'=>$location],true);
    }

    #[Route('/api/books/{id}', name: 'updateBook', methods: ['PUT'])]
    public function updateBook(
        Request $request,
        Book $currentBook, 
        EntityManagerInterface $em,
        SerializerInterface $serializer, 
        AuthorRepository $authorRepository,
        ValidatorInterface $validator,
        TagAwareCacheInterface $cache
        
        ): JsonResponse {

        $newBook = $serializer->deserialize($request->getContent(),Book::class, 'json');
        $currentBook->setTitle($newBook->getTitle());
        $currentBook->setCoverText($newBook->getCoverText());

        // On vérifie les erreurs
        $errors = $validator->validate($currentBook);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $content=$request->toArray();
        $idAuthor=$content['idAuthor'];
        $currentBook->setAuthor($authorRepository->find($idAuthor));

        $em->persist($currentBook );
        $em->flush();   

        // On vide le cache.
        $cache->invalidateTags(["booksCache"]);      

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }    

}
