// This plugins also need "WP Crontrol"

function twohr_custom_cron_schedule($schedules)
{
    $schedules['every_two_hours'] = array(
        'interval' => 7200, // Every 2 hours
        'display'  => __('Every 2 hours'),
    );
    return $schedules;
}
add_filter('cron_schedules', 'twohr_custom_cron_schedule');

//Schedule an action if it's not already scheduled
if (!wp_next_scheduled('get_data_from_software')) {
    wp_schedule_event(time(), 'every_two_hours', 'get_data_from_software');
}

//Schedule an action if it's not already scheduled
if (!wp_next_scheduled('get_data_from_software')) {
    wp_schedule_event(time(), 'every_two_hours', 'get_data_from_software');
}

///Hook into that action that'll fire every two hours
add_action('get_data_from_software', 'import_apartments_from_xml');


function import_apartments_from_xml()
{

    // URL of the XML file
    $url = 'https://rodarealestatecomo.com/csvinfo/roda@rodarealestatecomo.com_20240528150848.xml';

    // Load the XML file
    $xml = simplexml_load_file($url);

    if ($xml === false) {
        die('Error: Cannot create object');
    }

    // Extract the "immobilie" section
    $appartments = $xml->anbieter->immobilie;

    // Initialize an array to store combined data
    $apparmens_data = array();

    // Iterate over each "immobilie" entry
    foreach ($appartments as $immobilie) {
        // Extract data from "kontaktperson"
        $kontaktperson = $immobilie->kontaktperson;

        // Extract data from "preise"
        $preise = $immobilie->preise;

        // Extract data from "flaechen"
        $flaechen = $immobilie->flaechen;

        // Extract "freitexte" and "objekttitel"
        $freitexte = $immobilie->freitexte;

        // Extract image URLs from "anhaenge"
        $images = array();
        $baseImageUrl = 'https://rodarealestatecomo.com/csvinfo/images/';
        if (isset($immobilie->anhaenge)) {
            foreach ($immobilie->anhaenge->anhang as $anhang) {
                if (isset($anhang->daten->pfad)) {
                    $images[] = $baseImageUrl . (string)$anhang->daten->pfad;
                }
            }
        }
        $uniq_imgs = array_unique($images);

        // Extract data from "verwaltung_techn"
        $verwaltungTechn = $immobilie->verwaltung_techn;

        // Construct the combined data array
        $apparmens_data[] = array(
            'email_zentrale'        => (string)$kontaktperson->email_zentrale,
            'email_direkt'          => (string)$kontaktperson->email_direkt,
            'tel_zentrale'          => (string)$kontaktperson->tel_zentrale,
            'tel_durchw'            => (string)$kontaktperson->tel_durchw,
            'name'                  => (string)$kontaktperson->name,
            'vorname'               => (string)$kontaktperson->vorname,
            'anrede'                => (string)$kontaktperson->anrede,
            'firma'                 => (string)$kontaktperson->firma,
            'strasse'               => (string)$kontaktperson->strasse,
            'hausnummer'            => (string)$kontaktperson->hausnummer,
            'plz'                   => (string)$kontaktperson->plz,
            'ort'                   => (string)$kontaktperson->ort,
            'url'                   => (string)$kontaktperson->url,
            'personennummer'        => (string)$kontaktperson->personennummer,
            'kaufpreis'             => (string)$preise->kaufpreis,
            'hausgeld'              => (string)$preise->hausgeld,
            'kaufpreis_pro_qm'      => (string)$preise->kaufpreis_pro_qm,
            'anzahl_zimmer'         => (string)$flaechen->anzahl_zimmer,
            'anzahl_schlafzimmer'   => (string)$flaechen->anzahl_schlafzimmer,
            'anzahl_badezimmer'     => (string)$flaechen->anzahl_badezimmer,
            'anzahl_balkone'        => (string)$flaechen->anzahl_balkone,
            'objektnr_intern'       => (string)$verwaltungTechn->objektnr_intern,
            'objektnr_extern'       => (string)$verwaltungTechn->objektnr_extern,
            'openimmo_obid'         => (string)$verwaltungTechn->openimmo_obid,
            'kennung_ursprung'      => (string)$verwaltungTechn->kennung_ursprung,
            'stand_vom'             => (string)$verwaltungTechn->stand_vom,
            'weitergabe_generell'   => (string)$verwaltungTechn->weitergabe_generell,
            'objekttitel'           => (string)$freitexte->objekttitel,
            'objektbeschreibung'    => (string)$freitexte->objektbeschreibung,
            'sonstige_angaben'      => (string)$freitexte->sonstige_angaben,
            'images'                => $uniq_imgs
        );
    }


    function upload_image_from_url_to_post($image_url, $post_id)
    {
        // Check if the image already exists in the media library.
        $image_name = basename($image_url);
        $existing_attachment = get_page_by_title($image_name, 'OBJECT', 'attachment');

        // If the image already exists, return its ID.
        if ($existing_attachment) {
            return $existing_attachment->ID;
        }

        // If the image doesn't exist, download and upload it to the media library.
        $image_data = file_get_contents($image_url);
        if ($image_data) {
            $upload_dir  = wp_upload_dir();
            $upload_path = $upload_dir['path'] . '/' . $image_name;
            $upload_file = file_put_contents($upload_path, $image_data);

            // Check if the image was successfully uploaded.
            if ($upload_file) {
                $wp_filetype = wp_check_filetype($upload_path, null);
                $attachment = array(
                    'post_mime_type'     => $wp_filetype['type'],
                    'post_title'         => sanitize_file_name($image_name),
                    'post_content'         => '',
                    'post_status'         => 'inherit'
                );
                $attachment_id = wp_insert_attachment($attachment, $upload_path, $post_id);
                if (!is_wp_error($attachment_id)) {
                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload_path);
                    wp_update_attachment_metadata($attachment_id, $attachment_data);
                    return $attachment_id;
                }
            }
        }

        return false;
    }

    function upload_images_to_posts($image_urls, $post_ids)
    {
        foreach ($post_ids as $post_id) {
            $gallery_ids = array();
            foreach ($image_urls as $image_url) {
                $attachment_id = upload_image_from_url_to_post($image_url, $post_id);
                if ($attachment_id) {
                    $gallery_ids[] = $attachment_id;
                } else {
                    // echo "Failed to upload image from URL $image_url to post ID $post_id.\n";
                }
            }
            // Update post meta with the array of attachment IDs for the gallery
            update_post_meta($post_id, 'aptsm_gallery', $gallery_ids);
        }
    }

    foreach ($apparmens_data as $single_data) {

        $title_to_check         = $single_data['objekttitel'] ?? '';
        $objektbeschreibung     = $single_data['objektbeschreibung'] ?? '';
        $sonstige_angaben       = $single_data['sonstige_angaben'] ?? '';
        $email_zentrale         = $single_data['email_zentrale'] ?? '';
        $email_direkt           = $single_data['email_direkt'] ?? '';
        $tel_zentrale           = $single_data['tel_zentrale'] ?? '';
        $tel_durchw             = $single_data['tel_durchw'] ?? '';
        $name                   = $single_data['name'] ?? '';
        $vorname                = $single_data['vorname'] ?? '';
        $anrede                 = $single_data['anrede'] ?? '';
        $firma                  = $single_data['firma'] ?? '';
        $strasse                = $single_data['strasse'] ?? '';
        $hausnummer             = $single_data['hausnummer'] ?? '';
        $apt_plz                = $single_data['plz'] ?? '';
        $apt_ort                = $single_data['ort'] ?? '';
        $apt_url                = $single_data['url'] ?? '';
        $personennummer         = $single_data['personennummer'] ?? '';
        $kaufpreis              = $single_data['kaufpreis'] ?? '';
        $hausgeld               = $single_data['hausgeld'] ?? '';
        $kaufpreis_pro_qm       = $single_data['kaufpreis_pro_qm'] ?? '';
        $anzahl_zimmer          = $single_data['anzahl_zimmer'] ?? '';
        $anzahl_schlafzimmer    = $single_data['anzahl_schlafzimmer'] ?? '';
        $anzahl_badezimmer      = $single_data['anzahl_badezimmer'] ?? '';
        $anzahl_balkone         = $single_data['anzahl_balkone'] ?? '';
        $objektnr_intern        = $single_data['objektnr_intern'] ?? '';
        $objektnr_extern        = $single_data['objektnr_extern'] ?? '';
        $openimmo_obid          = $single_data['openimmo_obid'] ?? '';
        $kennung_ursprung       = $single_data['kennung_ursprung'] ?? '';
        $stand_vom              = $single_data['stand_vom'] ?? '';
        $weitergabe_generell    = $single_data['weitergabe_generell'] ?? '';
        $gallery_images         = $single_data['images'] ?? '';

        $args = array(
            'post_type' => 'apartment',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'title' => $title_to_check
        );

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_ID = get_the_ID();

                $post_ids = array($post_ID);
                upload_images_to_posts($gallery_images, $post_ids);

                // start insert postr meta 

                update_post_meta($post_ID, 'objektbeschreibung', $objektbeschreibung);
                update_post_meta($post_ID, 'sonstige_angaben', $sonstige_angaben);
                update_post_meta($post_ID, 'email_direkt', $email_direkt);
                update_post_meta($post_ID, 'email_zentrale', $email_zentrale);
                update_post_meta($post_ID, 'tel_zentrale', $tel_zentrale);
                update_post_meta($post_ID, 'tel_durchw', $tel_durchw);
                update_post_meta($post_ID, 'name', $name);
                update_post_meta($post_ID, 'vorname', $vorname);
                update_post_meta($post_ID, 'anrede', $anrede);
                update_post_meta($post_ID, 'firma', $firma);
                update_post_meta($post_ID, 'strasse', $strasse);
                update_post_meta($post_ID, 'hausnummer', $hausnummer);
                update_post_meta($post_ID, 'apt_plz', $apt_plz);
                update_post_meta($post_ID, 'apt_ort', $apt_ort);
                update_post_meta($post_ID, 'apt_url', $apt_url);
                update_post_meta($post_ID, 'personennummer', $personennummer);
                update_post_meta($post_ID, 'kaufpreis', $kaufpreis);
                update_post_meta($post_ID, 'hausgeld', $hausgeld);
                update_post_meta($post_ID, 'kaufpreis_pro_qm', $kaufpreis_pro_qm);
                update_post_meta($post_ID, 'anzahl_zimmer', $anzahl_zimmer);
                update_post_meta($post_ID, 'anzahl_schlafzimmer', $anzahl_schlafzimmer);
                update_post_meta($post_ID, 'anzahl_badezimmer', $anzahl_badezimmer);
                update_post_meta($post_ID, 'anzahl_balkone', $anzahl_balkone);
                update_post_meta($post_ID, 'objektnr_intern', $objektnr_intern);
                update_post_meta($post_ID, 'objektnr_extern', $objektnr_extern);
                update_post_meta($post_ID, 'openimmo_obid', $openimmo_obid);
                update_post_meta($post_ID, 'kennung_ursprung', $kennung_ursprung);
                update_post_meta($post_ID, 'stand_vom', $stand_vom);
                update_post_meta($post_ID, 'weitergabe_generell', $weitergabe_generell);

                // end insert postr meta 
            }
        } else {

            $new_apart = array(
                'post_title'    => $title_to_check,
                'post_status'   => 'publish',
                'post_type'     => 'apartment',
            );

            $post_ID = wp_insert_post($new_apart);
            $post_ids = array($post_ID);
            upload_images_to_posts($gallery_images, $post_ids);

            // start insert postr meta 
            update_post_meta($post_ID, 'objektbeschreibung', $objektbeschreibung);
            update_post_meta($post_ID, 'sonstige_angaben', $sonstige_angaben);
            update_post_meta($post_ID, 'email_direkt', $email_direkt);
            update_post_meta($post_ID, 'email_zentrale', $email_zentrale);
            update_post_meta($post_ID, 'tel_zentrale', $tel_zentrale);
            update_post_meta($post_ID, 'tel_durchw', $tel_durchw);
            update_post_meta($post_ID, 'name', $name);
            update_post_meta($post_ID, 'vorname', $vorname);
            update_post_meta($post_ID, 'anrede', $anrede);
            update_post_meta($post_ID, 'firma', $firma);
            update_post_meta($post_ID, 'strasse', $strasse);
            update_post_meta($post_ID, 'hausnummer', $hausnummer);
            update_post_meta($post_ID, 'apt_plz', $apt_plz);
            update_post_meta($post_ID, 'apt_ort', $apt_ort);
            update_post_meta($post_ID, 'apt_url', $apt_url);
            update_post_meta($post_ID, 'personennummer', $personennummer);
            update_post_meta($post_ID, 'kaufpreis', $kaufpreis);
            update_post_meta($post_ID, 'hausgeld', $hausgeld);
            update_post_meta($post_ID, 'kaufpreis_pro_qm', $kaufpreis_pro_qm);
            update_post_meta($post_ID, 'anzahl_zimmer', $anzahl_zimmer);
            update_post_meta($post_ID, 'anzahl_schlafzimmer', $anzahl_schlafzimmer);
            update_post_meta($post_ID, 'anzahl_badezimmer', $anzahl_badezimmer);
            update_post_meta($post_ID, 'anzahl_balkone', $anzahl_balkone);
            update_post_meta($post_ID, 'objektnr_intern', $objektnr_intern);
            update_post_meta($post_ID, 'objektnr_extern', $objektnr_extern);
            update_post_meta($post_ID, 'openimmo_obid', $openimmo_obid);
            update_post_meta($post_ID, 'kennung_ursprung', $kennung_ursprung);
            update_post_meta($post_ID, 'stand_vom', $stand_vom);
            update_post_meta($post_ID, 'weitergabe_generell', $weitergabe_generell);

            // end insert postr meta 
        }

        wp_reset_query();
    }
}

