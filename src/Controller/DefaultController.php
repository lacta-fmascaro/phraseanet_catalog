<?php

namespace App\Controller;

use App\Service\Api;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class DefaultController
 * @package App\Controller
 */
class DefaultController extends AbstractController
{
    /**
     * @Route("/", name="app_home")
     */
    public function indexAction(Request $request, Api $api, $page = 1)
    {
        return $this->redirectToRoute('app_search');
    }

    /**
     * @Route("/search", name="app_search")
     */
    public function searchAction(Request $request, Api $api, $page = 1)
    {
        return $this->render('search.html.twig');
    }

    /**
     * @Route("/search/records/{page}", name="app_search_records", requirements={"page"="\d+"}, defaults={"page"=1}, methods={"POST", "GET"})
     */
    public function searchRecordsAction(Request $request, Api $api, $page)
    {
        $perPage = 6;
        $query = '';
        $parameters = array();

        $requestContent = $request->getContent();

        if ($api->isJson($requestContent)) {
            $parameters = json_decode($requestContent, true, JSON_INVALID_UTF8_IGNORE);
            $query = $parameters['search_keywords'];

            $selectedFacets = array();
            $i = 0;

            foreach ($parameters as $index => $parameter) {
                if ($parameter != '') {
                    if ($query == '' and $i == 0) {
                        $query .= $parameter;
                    } else {
                        $query .= ' ET ' . $parameter;
                    }
                    $i++;
                    $selectedFacets[$index] = $parameter;
                }
            }
        }

        // Construction de la query (KEYWORDS ET field.Pays="VALUE" ET field.MotsCles="VALUE")

        $objects = $api->getRecords($query, $page, $perPage);

        // Create facets array

        $facets = array();

        $facetsToDisplay = array('field.BrandName', 'field.ShelfLife', 'field.Plant', 'field.Keywords', 'field.ProductType', 'field.ProductFormat');

        foreach ($objects['facets'] as $facet) {

            if (in_array($facet['field'], $facetsToDisplay)) {
                $facets[$facet['field']]['name'] = $facet['name'];
                $facets[$facet['field']]['field'] = $facet['field'];

                $values = array();

                foreach ($facet['values'] as $value) {

                    $val = array();
                    $val['value'] = $value['value'];
                    $val['query'] = $value['query'];


                    if (isset($selectedFacets[$facet['field']]) and $selectedFacets[$facet['field']] == $value['query']) {
                        $val['selected'] = true;
                    } else {
                        $val['selected'] = false;
                    }

                    $values[] = $val;
                }
                $facets[$facet['field']]['values'] = $values;
            }
        }

        $data = array();

        if (isset($objects['search']) and $objects['search']['total'] == 0) {

            $data['objects'] = array();
            $data['page'] = $page;
            $data['page_number'] = ceil($objects['search']['total'] / $perPage);
            $data['query'] = $parameters;
            $data['facets'] = $facets;

        } else {
            $data['objects'] = $objects['records'];
            $data['page'] = $page;
            $data['page_number'] = ceil($objects['search']['total'] / $perPage);
            $data['query'] = $parameters;
            $data['facets'] = $facets;
        }

        return new JsonResponse($data);
    }


}
