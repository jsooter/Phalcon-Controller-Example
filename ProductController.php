<?php
namespace Multiple\Manager\Controllers;
/*
Multiple\Manager\Controllers\ProductController
====
*/
class ProductController extends \Phalcon\Mvc\Controller
{
    /*
    indexAction()
    ====
    Landing page
    */
    public function indexAction(){

    }
    /*
    javascriptAction()
    ====
    Serve dynamic javascript files
    */
    public function javascriptAction(){
        $auth = $this->session->get('auth');
        $user = \Multiple\Models\User::findFirst(array(
            "username = :username:",
            "bind" => array(
                "username" => $auth['username']
            )
        ));
        $this->view->setVar('user',$user);
        $params = $this->dispatcher->getParams();
        $view   = $params[0];
        if(count($params) > 1){
            $view = implode('/',$params);
        }
        header( "Content-Type: text/javascript; charset=utf-8"  );
        $this->view->pick("product/javascript/".$view);
    }
    /*
    getProductsAction()
    ====
    Get products in JSON format
    */
    public function getProductsAction(){
        $products = \Multiple\Models\Product::find(array(
            "deleted IS NULL"
        ));
        $results = array();
        foreach($products as $product){
            $results[] = \Helpers\Model::toArray($product);
        }
        \Helpers\Controller::jsonify($results);
        $this->view->disable();
    }
    /*
    getProductAction()
    ====
    Get a product in JSON format

    Parameters
    ----
    id : integer _[product/getproduct/:id]_
    */
    public function getProductAction(){
        $params = $this->dispatcher->getParams();
        $id = array_shift($params);
        $product = \Multiple\Models\Product::findFirst(array(
            "id =:id:",
            "bind" => array(
                "id" => $id
            )
        ));
        $result = \Helpers\Model::toArray($product);
        \Helpers\Controller::jsonify($result);
        $this->view->disable();
    }
    /*
    updateProductAction()
    ====
    Updates a product

    Parameters
    ----
    id : integer
    name : string
    description : string
    */
    public function updateProductAction(){
        $reponse = array(
            'status' => \Helpers\Language::$postStatusFailure,
            'message' => \Helpers\Language::$errorPostParameters
        );
        // must be a POST request
        if ($this->request->isPost() == true) {
            $params = $this->request->getPost();
            $product = \Multiple\Models\Product::findFirst(array(
                "id = :id:",
                "bind" => array(
                    "id" => $params['id']
                )
            ));
            $response['message'] = 'Product does not exist.';
            if($product){
                foreach($params as $key => $value){
                    \Helpers\Model::setUpdateProperty($product,$key,$value);
                }
                $auth = $this->session->get('auth');
                $user = \Multiple\Models\User::findFirst(array(
                    "username = :username:",
                    "bind" => array(
                        "username" => $auth['username']
                    )
                ));
                $product->updated = 'NOW()';
                $product->updated_by = $user->username;
                $response['message'] = 'Failed to update product.';
                if($product->update()){
                    $response['status'] = \Helpers\Language::$postStatusSuccess;
                    $response['message'] = 'Product updated successfully.';
                    $response['id'] = $product->id;
                }
            }
        }
        \Helpers\Controller::jsonify($response);
        $this->view->disable();
    }
    /*
    createProductAction()
    ====
    Creates a product

    Parameters
    ----
    name : string
    description : string
    */
    public function createProductAction(){
        $reponse = array(
            'status' => \Helpers\Language::$postStatusFailure,
            'message' => \Helpers\Language::$errorPostParameters
        );
        // must be a POST request
        if ($this->request->isPost() == true) {
            $params = $this->request->get();
            $product = new \Multiple\Models\Product();
            foreach($params as $key => $value){
                \Helpers\Model::setCreateProperty($product,$key,$value);
            }
            $auth = $this->session->get('auth');
            $user = \Multiple\Models\User::findFirst(array(
                "username = :username:",
                "bind" => array(
                    "username" => $auth['username']
                )
            ));
            $product->created = 'NOW()';
            $product->created_by = $user->username;
            $response['message'] = 'Failed to create product';
            if($product->create()){
                $response['status'] = \Helpers\Language::$postStatusSuccess;
                $response['message'] = 'Product created successfully';
                $response['id'] = $product->id;
            }
        }
        \Helpers\Controller::jsonify($response);
        $this->view->disable();
    }
    /*
    deleteProductAction()
    ====
    Marks product as deleted

    Parameters
    ----
    id : integer
    */
    public function deleteProductAction(){
        $reponse = array(
            'status' => \Helpers\Language::$postStatusFailure,
            'message' => \Helpers\Language::$errorPostParameters
        );
        // must be a POST request
        if($this->request->isPost() == true){
            $params = $this->request->get();
            $product = \Multiple\Models\Product::findFirst(array(
                "id = :id:",
                "bind" => array(
                    "id" => $params['id']
                )
            ));
            $response['message'] = 'Product does not exist.';
            if($product){
                $auth = $this->session->get('auth');
                $user = \Multiple\Models\User::findFirst(array(
                    "username = :username:",
                    "bind" => array(
                        "username" => $auth['username']
                    )
                ));
                $product->deleted = 'NOW()';
                $product->deleted_by = $user->username;
                $response['message'] = 'Product failed to update';
                if($product->update()){
                    $response['status'] = \Helpers\Language::$postStatusSuccess;
                    $response['message'] = 'Product updated successfully';
                    $response['id'] = $product->id;
                }
            }
        }
        \Helpers\Controller::jsonify($response);
        $this->view->disable();
    }
    /*
    searchProductAction()
    ====
    API endpoint for product search

    Parameters
    ----
    search : string
    */
    public function searchProductAction(){
        $search = $this->request->get("search"); // search first and last name
        $products = $this::searchProducts($search);
        foreach($products as $product){
            $b = (object) $product;
            $results[] = array(
                "value" => $search,
                "title" => $b->name,
                "url" => "#",
                "text" => $b->description,
            );
        }
        \Helpers\Controller::jsonify($results);
        $this->view->disable();
    }
    /*
    searchProducts()
    ====
    Search products

    Parameters
    ----
    search : string
    */
    public static function searchProducts($search){
        if(!empty($search)){
            // check for multiple search terms
            if(strpos($search,' ') !== false){
                $searchArray = explode(" ", $search);
                $firstSearch = '%'.array_shift($searchArray).'%';
            } else {
                $firstSearch = '%'.$search.'%';
            }
            $params = array(
                "lower(name) LIKE lower(:s:)
                    OR description LIKE :s:",
                "bind" => array(
                    "s" => $firstSearch
                )
            );
        } else {
            $params = null;
        }
        $products = \Multiple\Models\Product::find($params);
        if(!empty($searchArray)){
            $products = $products->filter(function($product) use ($searchArray){
                foreach($searchArray as $s){
                    if (stripos($product->name,$s) ||
                        stripos($product->description,$s)) {
                            return $product;
                    }
                }
            });
        }
        if($products){
            return $products->toArray();
        } else {
            return false;
        }
    }
    /*
    productAttributesAction()
    ====
    Get a list of product attributes
    */
    public function productAttributesAction(){
        $list = array();
        $query = "SELECT json_object_keys(attributes) as attributes
            FROM product
            GROUP BY json_object_keys(attributes)
            ORDER BY json_object_keys(attributes)";
        $pdo = \Phalcon\DI::getDefault()->getDb();
        $sql = $pdo->prepare($query);
        $sql->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
        $sql->execute();
        $results = $sql->fetchAll();
        foreach($results as $result){
            $list[] = $result['attributes'];
        }
        \Helpers\Controller::jsonify($list);
        $this->view->disable();
    }
    /*
    uploadImageAction()
    ====
    */
    public function uploadImageAction(){
        $reponse = array(
            'status' => \Helpers\Language::$postStatusFailure,
            'message' => \Helpers\Language::$errorUploadFiles
        );
        // Check if the user has uploaded files
        if ($this->request->hasFiles()) {
            $error_flag = false;
            $params = $this->dispatcher->getParams();
            $product = \Multiple\Models\Product::findFirst(array(
                "id = :id:",
                "bind" => array(
                    "id" => $params[0]
                )
            ));
            if($product){
                //check to see if folder exists
                if(!is_dir($this->config->manager->product_image_base_path . $product->id)){
                    mkdir($this->config->manager->product_image_base_path . $product->id);
                }
                // Print the real file names and sizes
                foreach ($this->request->getUploadedFiles() as $file) {
                    $target_file = \Helpers\Controller::sanitizeFileName(realpath($this->config->manager->product_image_base_path.$product->id).'/'.$file->getName());
                    $url = \Helpers\Controller::sanitizeFileName($this->config->manager->product_image_base_url.$product->id.'/'.$file->getName());
                    // Move the file into the application
                    if($file->moveTo($target_file)){
                        $image = new \Phalcon\Image\Adapter\Gd($target_file);
                        if($image->getWidth() > 1024){
                            $image->resize(
                                1024,
                                null,
                                \Phalcon\Image::WIDTH
                            );
                        }
                        if($image->getMime() != 'image/jpeg'){
                            $target_file = basename($target_file).'.jpg';
                        }
                        $image->save($target_file, 80);
                        $response['status']  = \Helpers\Language::$postStatusSuccess;
                        $response['message'] = 'Files uploaded.';
                        $response['url'] = $url;
                    }
                }
            }
        }
        \Helpers\Controller::jsonify($response);
        $this->view->disable();
    }
    /*
    getProductImagesAction()
    ====
    */
    public function getProductImagesAction(){
        $params = $this->dispatcher->getParams();
        $product_id = array_shift($params);
        $results = array();
        if(is_dir($this->config->manager->product_image_base_path.$product->id)){
            $images = scandir($this->config->manager->product_image_base_path.$product->id);
            foreach($images as $image){
                if(substr($image,0,1) != '.'){
                    $results[] = $image;
                }
            }
        }
        \Helpers\Controller::jsonify($results);
        $this->view->disable();
    }
    /*
    getSkusAction()
    ====
    */
    public function getProductSkusAction(){
        $params = $this->dispatcher->getParams();
        $product_id = array_shift($params);
        $skus = \Multiple\Models\Sku::find(array(
            "product_id = :product_id: AND deleted IS NULL",
            "bind" => array(
                "product_id" => $product_id
            )
        ));
        $results = array();
        foreach($skus as $sku){
            $results[] = \Helpers\Model::toArray($sku);
        }
        \Helpers\Controller::jsonify($results);
        $this->view->disable();
    }
    /*
    getBrandAction()
    ====
    */
    public function getSkuAction(){
        $params = $this->dispatcher->getParams();
        $id = array_shift($params);
        $sku = \Multiple\Models\Brand::findFirst(array(
            "id =:id:",
            "bind" => array(
                "id" => $id
            )
        ));
        $result = \Helpers\Model::toArray($sku);
        \Helpers\Controller::jsonify($result);
        $this->view->disable();
    }
}
