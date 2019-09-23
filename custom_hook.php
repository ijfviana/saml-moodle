<?php
/*
SAML Authentication Plugin Custom Hook


This file acts as a hook for the SAML Authentication plugin. The plugin will
call the functions defined in this file in certain points in the plugin
lifecycle.

Use this sample file as a template. You should copy it and not modify it
in place since you may lost your changes in future updates.

To use this hook you have to go to the config form in the admin interface of
Moodle and set the full path to this file. Please note that the default value
for such a field is this custom_hook.php file itself.

You should not change the name of the funcions since that's the API the plugin
expect to exist and to use.

Read the comments of each function to discover when they are called and what
are they for.
*/


/*
 name: saml_hook_attribute_filter
 arguments:
   - $saml_attributes: array of SAML attributes
 return value:
   - nothing
 purpose: this function allows you to modify the array of SAML attributes.
          You can change the values of them (e.g. removing the non desired
          urn parts) or you can even remove or add attributes on the fly.
*/
function saml_hook_attribute_filter(&$saml_attributes) {

    // Nos quedamos sólamente con el DNI dentro del schacPersonalUniqueID
    if(isset($saml_attributes['schacPersonalUniqueID'])) {
        foreach($saml_attributes['schacPersonalUniqueID'] as $key => $value) {
            $data = array();
            if(preg_match('/urn:mace:terena.org:schac:personalUniqueID:es:(.*):(.*)/', $value, $data)) {
                $saml_attributes['schacPersonalUniqueID'][$key] = $data[2];
                //DNI sin letra
                //$saml_attributes['schacPersonalUniqueID'][$key] = substr($value[2], 0, 8);
            }
            else {
                unset($saml_attributes['schacPersonalUniqueID'][$key]);
            }
        }
    }

    // Pasamos el irisMailMainAddress como mail si no existe
    if(!isset($saml_attributes['mail'])) {
        if(isset($saml_attributes['irisMailMainAddress'])) {
            $saml_attributes['mail'] = $saml_attributes['irisMailMainAddress'];
        }
    }


    // Pasamos el uid como eduPersonPrincipalName o como eduPersonTargetedID
    if(!isset($saml_attributes['eduPersonPrincipalName'])) {
        if(isset($saml_attributes['uid'])) {
            $saml_attributes['eduPersonPrincipalName'] = $saml_attributes['uid'];
        }
        else if (isset($saml_attributes['eduPersonTargetedID'])) {
            $saml_attributes['eduPersonPrincipalName'] = $saml_attributes['eduPersonTargetedID'];
        }
        else if (isset($saml_attributes['mail'])) {
            $saml_attributes['eduPersonPrincipalName'] = $saml_attributes['mail'];
        }
    }


    // Pasamos el uid como eduPersonPrincipalName

    if(!isset($saml_attributes['eduPersonPrincipalName'])) {
        if(isset($saml_attributes['uid'])) {
            $saml_attributes['eduPersonPrincipalName'] = $saml_attributes['uid'];
        }
        else if (isset($saml_attributes['mail'])) {
            $saml_attributes['eduPersonPrincipalName'] = $saml_attributes['mail'];
        }
    }

   // Generamos un schacUserStatus vacio si no existe

   if(!isset($saml_attributes['schacUserStatus']))
        $saml_attributes['schacUserStatus'] = array();

   // Añadimos los cursos en los que se matricula todos los usuarioss

   $current_year = saml_uhu_currentCursoAca();
   $saml_attributes['schacUserStatus'][] = 'urn:mace:terena.org:schac:userStatus:es:uhu.es:SEV-ALU:'.$current_year.':student:active';

   // Los alumnos los matriculamos en el caruh
   if (isset($saml_attributes['eduPersonAffiliation']) && $saml_attributes['eduPersonAffiliation'][0] == "student")
   {
      $saml_attributes['schacUserStatus'][] = 'urn:mace:terena.org:schac:userStatus:es:uhu.es:CARUH:'.$current_year.':student:active';    
   }
   elseif (isset($saml_attributes['eduPersonAffiliation']) && $saml_attributes['eduPersonAffiliation'][0] == "member")
   {
      // Al resto en el manual para profesores
      $saml_attributes['schacUserStatus'][] = 'urn:mace:terena.org:schac:userStatus:es:uhu.es:SEV-PRO:'.$current_year.':student:active';
   }
}

/*
 name: saml_hook_user_exists
 arguments:
   - $username: candidate name of the current user
   - $saml_attributes: array of SAML attributes
   - $user_exists: true if the $username exists in Moodle database
 return value:
   - true if you consider that this username should exist, false otherwise.
 purpose: this function let you change the logic by which the plugin thinks
          the user exists in Moodle. You can even change the username if
          the user exists but you want to recreate with another name.
*/
function saml_hook_user_exists(&$username, $saml_attributes, $user_exists) {
    return true;
}

/*
 name: saml_hook_authorize_user
 arguments:
    - $username: name of the current user
    - $saml_attributes: array of SAML attributes
    - $authorize_user: true if the plugin thinks this user should be allowed
 return value:
    - true if the user should be authorized or an error string explaining
      why the user access should be denied.
 purpose: use this function to deny the access to the current user based on
          the value of its attributes or any other reason you want. It is
	  very important that this function return either true or an error
	  message.
*/
function saml_hook_authorize_user($username, $saml_attributes, $authorize_user) {
    return true;
}

/*
 name: saml_hook_post_user_created
 arguments:
   - $user: object containing the Moodle user
   - $saml_attributes: array of SAML attributes
 return value:
   - nothing
 purpose: use this function if you want to make changes to the user object
          or update any external system for statistics or something similar.
*/
function saml_hook_post_user_created($user, $saml_attributes = array()) {

}

/*
 name: saml_hook_get_course_info
 arguments:
   - $course: string that contains info about the course

 return array with the following indexes:
        0 - match      matched string
        1 - country    country info
        2 - domain     domain info
        3 - course_id  the course id to be mapped with moodle course
        4 - period     period of the course
        5 - role       role to be mappend with moodle role
        6 - status     'active' | 'inactive'
        7 - group      the group inside the course

  The auth/saml plugin save those data that will be available
  for the enrol/saml plugin.

  Right now only course_id, period, role and status are
  required, so if your Identity Provider don't retrieve country or domain info, return
  empty values for them Ex. alternative pattern
  Info: 'courseData:math1:2016-17:student:active'

  $regex = '/courseData:(.+):(.+):(.+):(.+):(.+):(.+)/';
  if (preg_match($regex, $course, $matches) {
    $regs = [];
    $regs[0] = $matches[0];
    $regs[1] = null;          // country
    $regs[2] = null;          // domain
    $regs[3] = $matches[1];   // course_id
    $regs[4] = $matches[2];   // period
    $regs[5] = $matches[3];   // role
    $regs[6] = $matches[4];   // status
    $regs[7] = null;          // group
  }
*/
function saml_hook_get_course_info($course) {
  $regs = null;

  $regex = '/urn:mace:terena.org:schac:userStatus:(.+):(.+):(.+):(.+):(.+):(.+)/';

  if (preg_match($regex, $course, $matches)) {
      $regs = $matches;
  }

  // Example retreving course from course_id
  // because course_id is like:  mat1-t1, mat1-t2 and t1 and t2 are
  // groups of course mat1
  $course_id = $regs[3];
  $data = explode("-", $course_id);
  
  if (isset($data[1])) {
  	$enrolpluginconfig = get_config('enrol_saml');
	$prefixes = $enrolpluginconfig->group_prefix;

  // Le añadimos un prefijo al nombre de los cursos
	if (!empty($prefixes)) {
		list($prefix) = explode(",",$prefixes);
	}else
		$prefix = 'UXXI_';
	
    // Si queremos agrupar a los alumnos de diferentes asignaturas que están matriculados en el mismo grupo
    // $regs[7] = $prefix.$data[1]; 
    // Si no queremos agrupar a los alumnos de diferentes asignaturas que están matriculados en el mismo grupo
    $regs[7] = $prefix.$course_id;
    $regs[3] =  $data[0].'-'.$regs[7];
  }


  // eliminamos las matriculaciones que no pertenezcan al curso académico 
  // actual
  $current_year = saml_uhu_currentCursoAca();
  
  if ($current_year != $regs[4])
  	return null;
  else{
  	return $regs;
  }
	
  // generamos atributo para appcrue

  list($crue,$trash) = explode('@', $saml_attributes['eduPersonPrincipalName'][0]);

  if (isset($saml_attributes['eduPersonAffiliation']) && $saml_attributes['eduPersonAffiliation'][0] == "student")
  {
      $crue_new=str_replace ( ".alu" , "" , $crue );
  }
  else
      $crue_new=$crue;

  $saml_attributes['appCrueID']=array($crue_new);
}


function saml_uhu_currentCursoAca()
    {
	      date_default_timezone_set ('Europe/Madrid' );
        $hoy = date('Y-m-d');
        $anio = date('Y');
        $begincurso = date('Y').'-10-01';

        if ($hoy >= $begincurso) {
            $curso = $anio.'-'.substr($anio + 1, -2);
        } else {
            $curso = $anio - 1 .'-'.substr($anio, -2);
        }

        return $curso;
    }
