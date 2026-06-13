<?php
namespace App\Controller;

use App\Entity\Infosportif;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InfosportifController extends AbstractController
{
  #[Route('/infosportif', name: 'app_infosportif')]
public function manageInfosportif(EntityManagerInterface $em): Response
{
    $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
    $user = $this->getUser();

    // Vérifie si l'utilisateur a déjà des infos
    $infosportif = $em->getRepository(Infosportif::class)->findOneBy(['sportif' => $user]);

    // Si aucune info n'existe, on en crée une avec des valeurs par défaut
    if (!$infosportif) {
        $infosportif = new Infosportif();
        $infosportif
            ->setPoids(70.0)    // Valeur par défaut
            ->setTaille(175.0)   // Valeur par défaut
            ->setAge(30)         // Valeur par défaut
            ->setSexe('Homme')   // Valeur par défaut
            ->setObjectif('ENDURANCE')
            ->setSportif($user);

        $em->persist($infosportif);
        $em->flush();

        $this->addFlash('success', 'Vos informations sportives ont été initialisées !');
    }

    return $this->render('sportif/infosportif.html.twig', [
        'infosportif' => $infosportif,
    ]);
}
#[Route('/infosportif/edit', name: 'app_infosportif_edit', methods: ['POST'])]
    public function editInfosportif(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();

        // Récupérer l'entité Infosportif
        $infosportif = $em->getRepository(Infosportif::class)->findOneBy(['sportif' => $user]);
        if (!$infosportif) {
            $this->addFlash('error', 'Aucun profil sportif trouvé.');
            return $this->redirectToRoute('app_infosportif');
        }

        // Récupérer les données du formulaire
        $poids = $request->request->get('poids', null);
        $taille = $request->request->get('taille', null);
        $age = $request->request->get('age', null);
        $sexe = $request->request->get('sexe', null);
        $objectif = $request->request->get('objectif', null);

        // Mettre à jour l'entité
        $infosportif
            ->setPoids($poids !== null ? (float)$poids : null)
            ->setTaille($taille !== null ? (float)$taille : null)
            ->setAge($age !== null ? (int)$age : null)
            ->setSexe($sexe)
            ->setObjectif($objectif);

        // Valider les données
        /*$errors = $validator->validate($infosportif);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error->getMessage());
            }
            return $this->redirectToRoute('app_infosportif');
        }*/

        // Persister les modifications
        try {
            $em->persist($infosportif);
            $em->flush();
            $this->addFlash('success', 'Vos informations sportives ont été mises à jour !');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_infosportif');
    }

}