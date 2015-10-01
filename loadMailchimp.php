<?php

/*
Nota:En este fichero esta toda la comunicacion con la api de mailchimp y la inicializacion y 
     carga de arrays con todos los datos de esta 
*/


//si la ersion de php es menor a la 5.5 es necesario incluir: include 'array_column.php';

//Evitar muestre advertencia de mysql deprecated driver
error_reporting(E_ALL ^ E_DEPRECATED);


//Configurar la hora del servidor
date_default_timezone_set('Europe/Madrid');

//Modificar la impresion de pantalla de variables por medio de var_dump() para depurar mejor
ini_set('xdebug.var_display_max_depth', 50);
ini_set('xdebug.var_display_max_children', 100000);
ini_set('xdebug.var_display_max_data', 100000);


 //Claves de la API de MAILCHIMP
 $keyAPI='Fill out this';
 $idListAPI='Fill out this';



 //Variables usadas para los suscriptos
 $msIdCliente= array();
 $totalDeSuscriptores;
 $msNombre1= array();
 $msIntereses= array();
 $msEmail= array();
 $msLastChanged=array();


 //Cargar la lista cleaners
 $totalDeCleaned;
 $mcEmail = array();  

 //Variables usadas para los no suscriptos
 $totalDeNoSuscriptos=0; 
 $muEmail= array();

 //Creacion de los arrays de cada letra del abecedario para cada una de las 3 diferentes lista de Mailchimp
 foreach($arrayLetrasAZ= range('a', 'z') as $letras) 
 {
      $msEmailOrganizados[$letras]  = array();
      $mcUnSusEmailOrganizados[$letras]  = array();  
      $mcCleanerEmailOrganizados[$letras]  = array();  
 } 

//Llamdas a las funciones
llamarSuscriptos();
calculaFechas();
llamarUnsubscribes();
llamarCleaneds(); 



//Clase para hacer las llamadas a el servidor de mailchimp mediante su API
class MailChimp
{

    private $api_key;
    private $api_endpoint = 'https://<dc>.api.mailchimp.com/2.0';
    //    private $verify_ssl   = false;
    /**
     * Create a new instance
     * @param string $api_key Your MailChimp API key
     */
    public function __construct($api_key)
    {
        $this->api_key = $api_key;
        list(, $emailcentre) = explode('-', $this->api_key);
        $this->api_endpoint = str_replace('<dc>', $emailcentre, $this->api_endpoint);
    }

    /**
     * Call an API method. Every request needs the API key, so that is added automatically -- you don't need to pass it in.
     * @param  string $method The API method to call, e.g. 'lists/list'
     * @param  array  $args   An array of arguments to pass to the method. Will be json-encoded for you.
     * @return array          Associative array of json decoded API response.
     */
    public function call($method, $args = array(), $timeout = 10)
    {
        return $this->makeRequest($method, $args, $timeout);
    }

    /**
     * Performs the underlying HTTP request. Not very exciting
     * @param  string $method The API method to be called
     * @param  array  $args   Assoc array of parameters to be passed
     * @return array          Assoc array of decoded result
     */
    private function makeRequest($method, $args = array(), $timeout = 10)

    {   //porner apikey en una variable no borrar
        $args['apikey'] = $this->api_key;
        $url = $this->api_endpoint.'/'.$method.'.json';
        $json_data = json_encode($args);
        if (function_exists('curl_init') && function_exists('curl_setopt')) 
        {
            //// create curl resource 
            $ch = curl_init();

            //// set url 
            curl_setopt($ch, CURLOPT_URL, $url);
            //curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            //curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-MCAPI/2.0');
        
            ////return the transfer/response as a string true=1  
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            
            //and 0 for a get request
            curl_setopt($ch, CURLOPT_POST, true);
                       
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);

            // $output contains the output string 
            $result = curl_exec($ch);

            // close curl resource to free up system resources 
            curl_close($ch);
        } 
        else
         {
            $result    = file_get_contents($url, null, stream_context_create(array(
                          'http' => array(
                          'protocol_version' => 1.1,
                          'user_agent'       => 'PHP-MCAPI/2.0',
                          'method'           => 'POST',
                          'header'           => "Content-type: application/json\r\n".
                                          "Connection: close\r\n" .
                                          "Content-length: " . strlen($json_data) . "\r\n",
                          'content'          => $json_data,
                          ),
                        )));
        }     
    return $result ? json_decode($result, true) : false;
    }
 }


//Obtener el numero de suscriptores de cada una de las listas para luego usarlos como iteradores
function dameNumeroDeSuscriptores($tipoDeSuscriptor,$MailChimp) 
{

  $consultaAPI=$MailChimp->call('lists/list');
  $encontrado=false;
  $contador=0;
  while (!$encontrado)
  {
      if  ((($consultaAPI['data'][$contador]['name']) == "general") || (($consultaAPI['data'][$contador]['name']) == "General"  )) 
      {
            $encontrado=true;
      }
      else 
      {
        $contador++;
      }
  }

  $total = ($consultaAPI['data'][$contador]['stats'][$tipoDeSuscriptor]);
  return $total;
}


//Funcion para actualizar en la lista de Mailchimo ciertos valores como .con por .com
function actualizaSuscriptor($emailAntiguo,$emailNuevo)
{

  global $keyAPI;
  global $idListAPI;
  
  $MailChimp = new MailChimp($keyAPI);
  $MailChimp->call('lists/update-member', array(
                    'apikey'            => $keyAPI,
                    'id'                => $idListAPI,
                    'email'             => array('email'=>$emailAntiguo),
                    'merge_vars'        => array('EMAIL'=>$emailNuevo),
                    //'update_existing'   => true,
                    'double_optin'      => false,
                    'replace_interests' => false,
                    'send_welcome'      => false
                
                  ));
                     
                    

}


//Funcion para establecer los criterios para actualizar o cambiar los datos de los usuarios en el servidor de mailchimp
function checkEmailMailchimp($email,$actualiza)
{
   $emailNuevo=$email;
   if(preg_match('/[A-Z]/', $email))
   {
      $emailNuevo=  strtolower($email);
   }
   if (preg_match('/.con$/',$email)) 
   {
      $emailNuevo=  preg_replace('/.con$/', '.com', $email);
      if ($actualiza) 
      {
          actualizaSuscriptor($email,$emailNuevo);
      }
   }
   if (preg_match('/.conn$/',$email)) 
   {
      $emailNuevo=  preg_replace('/.conn$/', '.com', $email);
      if ($actualiza) 
      {
          actualizaSuscriptor($email,$emailNuevo);
      }
   }
   return $emailNuevo;
 }


/*******************************************************************************************************************************
'*******************************************************************************************************************************
'CARGA CLEANED MAILCHIMP
'*******************************************************************************************************************************
'*******************************************************************************************************************************/

//Mete cada email en el array correspondiente segun su letra inicial
function organizaCleaners($correo)
{
  global $mcCleanerEmailOrganizados;
  $encontrado=false;
  $letra=97; //letra 'a'
  while (($letra <= 122) && (!$encontrado))  {
      if ($correo[0]==chr($letra))
      {
          $mcCleanerEmailOrganizados[chr($letra)][] = $correo;
          $encontrado=true;
      }
  $letra++;      
  }
  if (!$encontrado) $mcCleanerEmailOrganizados['numericos'][] = $correo;            
}


//Descarga la lista de cleaners
function llamarCleaneds() 
{
  
  global $totalDeCleaned;
  global $keyAPI;
  global $idListAPI;
  global $mcEmail;
  
  $MailChimp = new MailChimp($keyAPI);
  $totalDeCleaned= dameNumeroDeSuscriptores( 'cleaned_count',$MailChimp);
  echo "\nCargando $totalDeCleaned Cleaneds, iniciado a ".date('d m Y H:i:s')."\n";; //borrar

  
  $limite=100; //Debido a que la api solo devuelve de 100 en 100 usuarios  
  //Para iterar por todas las paginas de la lista 
  for ($contadorDeCadaCienPaginas=0; $contadorDeCadaCienPaginas <(ceil($totalDeCleaned/100)) ; $contadorDeCadaCienPaginas++) 
  { 
    $json=($MailChimp->call('lists/members', array(
                                  'apikey'=> $keyAPI,
                                  'id'    => $idListAPI,
                                  'status' => 'cleaned',
                                  'opts' => array('start'=> $contadorDeCadaCienPaginas,   "limit" => $limite,'sort_field' => 'email',"sort_dir" => 'ASC')

                          ))); 
      //Para llegar al maximo de paginas segun la cantidad de usuarios
      if ( $contadorDeCadaCienPaginas==ceil($totalDeCleaned/100)-1 ) 
      {
          $limite=$totalDeCleaned % 100;
      }

      //Iterar por todos los usuarios de la lista y guardar sus datos en las variables creadas
      for ($contadorCadaUsuario=0; $contadorCadaUsuario <$limite ; $contadorCadaUsuario++) 
      { 
        $email=$json['data'][$contadorCadaUsuario]['merges']['EMAIL'];
        $mcEmail[]=checkEmailMailchimp($email,true);
        organizaCleaners(checkEmailMailchimp($email,false));
      }
  }
}


/*******************************************************************************************************************************
'*******************************************************************************************************************************
'CARGA UNSUSCRIBED MAILCHIMP
'*******************************************************************************************************************************
'*******************************************************************************************************************************/

//Mete cada email en el array correspondiente segun su letra inicial
function organizaUnsuscritos($correo)
{
  global $mcUnSusEmailOrganizados;
  $encontrado=false;
  $letra=97; //letra 'a'
  while (($letra <= 122) && (!$encontrado))  
  {
      if ($correo[0]==chr($letra))
      {
          $mcUnSusEmailOrganizados[chr($letra)][] = $correo;
          $encontrado=true;
      }
      $letra++;      
  }
  if (!$encontrado) 
  {
    $mcUnSusEmailOrganizados['numericos'][] = $correo;            
  }
}


 //Cargar la lista de unsuscriptos 
 function llamarUnsubscribes() 
 {

  global $idListAPI;
  global $keyAPI;  
  global $totalDeNoSuscriptos;
  global $muEmail;

  $MailChimp = new MailChimp($keyAPI);
  $totalDeNoSuscriptos= dameNumeroDeSuscriptores( 'unsubscribe_count',$MailChimp);
  
  echo "\nCargando $totalDeNoSuscriptos Unsubscribed, iniciado a ".date('d m Y H:i:s')."\n"; //borrar?
  $limite=100; //Debido a que la api solo devuelve de 100 en 100 usuarios

  //Para iterar por todas las paginas de la lista 
  for ($contadorDeCadaCienPaginas=0; $contadorDeCadaCienPaginas <(ceil($totalDeNoSuscriptos/100)) ; $contadorDeCadaCienPaginas++) 
  { 
      $json=($MailChimp->call('lists/members', array(
                                  'apikey' => $keyAPI,
                                  'id'     => $idListAPI,
                                  'status' => 'unsubscribed',
                                  'opts'   => array('start'=> $contadorDeCadaCienPaginas,   "limit" => $limite,'sort_field' => 'email',"sort_dir" => 'ASC')

                          ))); 
      //Para llegar al maximo de paginas segun la cantidad de usuarios
      if ( $contadorDeCadaCienPaginas==ceil($totalDeNoSuscriptos/100)-1 ) 
      {
        $limite=$totalDeNoSuscriptos % 100;
      }

      //Iterar por todos los usuarios de la lista y guardar sus datos en las variables creadas
      for ($contadorCadaUsuario=0; $contadorCadaUsuario <$limite ; $contadorCadaUsuario++) 
      { 
        $email=$json['data'][$contadorCadaUsuario]['merges']['EMAIL'];
        $muEmail[]=checkEmailMailchimp($email,True); // *** se puede mejorar el paso de true o false
        organizaUnsuscritos(checkEmailMailchimp($email,False));
      }    
    }
}


/*******************************************************************************************************************************
'*******************************************************************************************************************************
'CARGA SUSCRITOS MAILCHIMP
'*******************************************************************************************************************************
'******************************************************************************************************************************/


//Mete cada email en el array correspondiente segun su letra inicial
function organizaSuscritos($correo){
  global $msEmailOrganizados;
  $encontrado=false;
  $letra=97; //= letra 'a'
  while (($letra <= 122) && (!$encontrado))  
  {
      if ($correo[0]==chr($letra))  
      {
          $msEmailOrganizados[chr($letra)][] = $correo;
          $encontrado=true;
      }
      $letra++;      
  }
  if (!$encontrado)
  { 
    $msEmailOrganizados['numericos'][] = $correo;            
  }
}




//Cargar la lista de los suscriptos
function llamarSuscriptos()
{

  global $totalDeSuscriptores;
  global $msEmail;
  global $msIdCliente;
  global $msNombre1;
  global $msNombre2;
  global $msOrigen;
  global $msFrase;
  global $msVersionCarga;//Fecha y versión de carga//especial
  global $msCategoria;
  global $msIdCentro;
  global $msIntereses;
  global $msLastChanged;
  global $keyAPI;
  global $idListAPI;

  $MailChimp = new MailChimp($keyAPI);
  $totalDeSuscriptores= dameNumeroDeSuscriptores( 'member_count',$MailChimp);
  echo "\nCargando $totalDeSuscriptores Subscribed, iniciado a ".date('d m Y H:i:s')."\n";; //borrar?

  $limite=100; //Debido a que la api solo devuelve de 100 en 100 usuarios

  for ($contadorDeCadaCienPaginas=0; $contadorDeCadaCienPaginas <(ceil($totalDeSuscriptores/100)) ; $contadorDeCadaCienPaginas++) 
  {
    $json=($MailChimp->call('lists/members', array(
                                  'apikey' => $keyAPI,
                                  'id'     => $idListAPI,
                                  'status' => 'subscribed',
                                  'opts'   => array('start'=> $contadorDeCadaCienPaginas,   "limit" => $limite,'sort_field' => 'email',"sort_dir" => 'ASC') //DESC
                          )));// *** cambiar la apikey y la id por las verdaderas 

    //Para llegar al maximo de paginas segun la cantidad de usuarios
    if ($contadorDeCadaCienPaginas==ceil($totalDeSuscriptores/100)-1 )
    {
        $limite=$totalDeSuscriptores % 100;
    }
  
    //Iterar por todos los usuarios de la lista y guardar sus datos en las variables creadas
    for ($contadorCadaUsuario=0; $contadorCadaUsuario <$limite ; $contadorCadaUsuario++)
    { 
        $email=$json['data'][$contadorCadaUsuario]['merges']['EMAIL'];
        $msEmail[]=checkEmailMailchimp($email,True);
        organizaSuscritos(checkEmailMailchimp($email,False));
        

        $idCliente=$json['data'][$contadorCadaUsuario]['merges']['IDCLIENTE'];
        $msIdCliente[]=$idCliente;
      
        $nombre=$json['data'][$contadorCadaUsuario]['merges']['FNAME'];
        $msNombre1[]=$nombre;



        $intereses=$json['data'][$contadorCadaUsuario]['merges']['GROUPINGS'][0]['groups'];
        $msIntereses[]=asignaIntereses($intereses);

     }
  }
  
}

//Crea el array de intereses de cada suscriptor
function asignaIntereses($intereses)
{
  $arrayVuelta=array();
  if (!empty($intereses))
  {
  foreach ($intereses as $key => $value) 
            if ( ($value['interested'])== true)
              $arrayVuelta[]=($value['name']);
  }
  return $arrayVuelta;

}

//Descarga las fechas y las calcula
function calculaFechas()
{

    global $msLastChanged;
    global $msEmail;
    global $idListAPI;
    global $keyAPI;
    global $totalDeSuscriptores;

    echo "\nDescargando fechas...  iniciado a: ".date('d m Y H:i:s')."\n"; 
    $apikey = $keyAPI;
    $list_id = $idListAPI;
    $chunk_size = 4096; //Bytes
    $url = 'http://us1.api.mailchimp.com/export/1.0/list?apikey='.$apikey.'&id='.$list_id;
    $arrayFechaConEmail=array();
    $handle = @fopen($url,'r');
    if (!$handle) 
    {
      echo "failed to access url\n";
    } 
    else
    {
          $i = 0;
          $header = array();
          while (!feof($handle)) {
            $buffer = fgets($handle, $chunk_size);
            if (trim($buffer)!=''){
              $obj = json_decode($buffer);
              if ($i==0)
              {
                  //store the header row
                  $header = $obj;
              } 
              else
              { 
                  $arrayFechaConEmail[] = array(
                                              'email' =>$obj[0],
                                              'last_changed'=>date("Y-m-d H:i:s", strtotime("$obj[29] + 2 hours")));
              }
              $i++;
            }
      }
      fclose($handle);
    }
    echo "\nCalculando fechas... iniciado a: ".date('d m Y H:i:s')."\n"; 
    foreach ($msEmail as $key => $value) 
    {
        if (is_numeric($indice=array_search($value, array_column($arrayFechaConEmail,'email')))) 
        {
            $msLastChanged[$key]=$arrayFechaConEmail[$indice]['last_changed'];
        }
    }
}

//Funcion para escribir los resultados en Mailchimp
function actualizaTodo($batch)
{

  global $keyAPI;
  global $idListAPI;
  
  $MailChimp = new MailChimp($keyAPI);
  $MailChimp->call('/lists/batch-subscribe', array(
                                                  'apikey'            => $keyAPI,
                                                  'id'                => $idListAPI,
                                                  'batch'             => $batch,
                                                  'double_optin'      => false,
                                                  'update_existing'   => true,
                                                  'replace_interests' => false,
                                                  'send_welcome'      => false
                                                  

                  )); 
}


?>