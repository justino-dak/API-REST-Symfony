<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use JMS\Serializer\SerializerInterface;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\DeserializationContext;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Request;
// use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class AuthorController extends AbstractController
{
    #[Route('/api/authors', name: 'authors',methods:['GET'])]
    public function getAuthors(
        AuthorRepository $authorRepository,
        SerializerInterface $serializer,
        Request $request,
        TagAwareCacheInterface $cachePool
        ): JsonResponse
    {
        $page=$request->get('page',1);
        $limit=$request->get('limit',3);
        $idCache='getAllAuthors'.'-'.$page.'-'.$limit;

        $jsonAuthorList=$cachePool->get($idCache, function(ItemInterface $item) use($page,$limit,$authorRepository,$serializer){
            echo("CET AUTEUR N'EST PAS ENCORE AJOUTÉ AU CACHE");
            $item->tag("authorsCache");
            $contexe=SerializationContext::create()->setGroups('getAuthors');
            $authorList=$authorRepository->findAllWithPagination($page,$limit);
            return $serializer->serialize($authorList,'json',$contexe);
        });

        return new JsonResponse($jsonAuthorList,Response::HTTP_OK,['accept'=>'json'],true);
    }

    #[Route('/api/authors/{id}', name: 'detailAuthor',methods:['GET'])]
    public function detailAuthor(Author $author, SerializerInterface $serializer): JsonResponse
    {
        $contexe=SerializationContext::create()->setGroups('getAuthors');
        $jsonAuthor=$serializer->serialize($author,'json',$contexe);

        return new JsonResponse($jsonAuthor,Response::HTTP_OK,['accept'=>'json'],true);
    }  

    #[Route('/api/authors/{id}', name: 'deleteAuthor',methods:['DELETE'])]
    public function deleteAuthor(
        Author $author, 
        EntityManagerInterface $em,
        TagAwareCacheInterface $cachePool
        ): JsonResponse
    {
        $cachePool->invalidateTags(['authorsCache']);
        $em->remove($author);
        $em->flush();

        return new JsonResponse(null,Response::HTTP_NO_CONTENT);
    }   
    
    #[Route('/api/authors', name: 'createAuthor',methods:['POST'])]
    public function createAuthor(
        Request $request, 
        EntityManagerInterface $em,
        SerializerInterface $serializer,
        UrlGeneratorInterface $urlGenerator,
        ValidatorInterface $validator

        ): JsonResponse
    {
        $author=$serializer->deserialize($request->getContent(), Author::class,'json');

        //on vérifie les erreurs
        $errors=$validator->validate($author);

        if ($errors->count() >0) {
            return new JsonResponse($serializer->serialize($errors,'json'),Response::HTTP_BAD_REQUEST,[],true);
        //    throw new HttpException(JsonResponse::HTTP_BAD_REQUEST,"La requête est invalide");
        }
        $em->persist($author);
        $em->flush();
        $contexe=SerializationContext::create()->setGroups('getAuthors');
        $jsonAuthor=$serializer->serialize($author,'json',$contexe);

        $location=$urlGenerator->generate('detailAuthor',['id'=>$author->getId()],UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonAuthor,Response::HTTP_CREATED,['Location'=>$location],true);
    }    

    #[Route('/api/authors/{id}', name: 'updateAuthor',methods:['PUT'])]
    public function updateAuthor(
        Request $request,
        Author $currentAuthor, 
        EntityManagerInterface $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        TagAwareCacheInterface $cache
        ): JsonResponse
    {
        // On vérifie les erreurs
        $errors = $validator->validate($currentAuthor);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        //Recupration des donnees envoyees dans un object de classe Author.
        //ces donnes sont recuperer directement dans un object recuper à partir de l'id de l'url
        // $contexe=DeserializationContext::create()->setAttribute('target',$currentAuthor);
        // $author=$serializer->deserialize($request->getContent(), Author::class,'json',$contexe);
        $author=$serializer->deserialize($request->getContent(), Author::class,'json');

        $currentAuthor->setFirstName($author->getFirstName());
        $currentAuthor->setLastName($author->getLastName());
        // $em->persist($author);
        $em->persist($currentAuthor);

        $em->flush();

        // On vide le cache. 
        $cache->invalidateTags(["booksCache"]);
        $cache->invalidateTags(["authorsCache"]);


        return new JsonResponse(null,Response::HTTP_NO_CONTENT);
    }      



}
