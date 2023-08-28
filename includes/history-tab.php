<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
 ?>
    <div class="wrap">
        <h1><?php esc_html_e('History', 'ai-bot-writer'); ?></h1>
        <table id="aibot_history" class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Scheduling ID', 'ai-bot-writer'); ?></th>
                    <th><?php esc_html_e('Post Title', 'ai-bot-writer'); ?></th>
                    <th><?php esc_html_e('Status', 'ai-bot-writer'); ?></th>
                    <th><?php esc_html_e('Created', 'ai-bot-writer'); ?></th>
                    <th><?php esc_html_e('Updated', 'ai-bot-writer'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if(is_array($results)) foreach($results as $v):
                 
                    $creation_date = $v->creation_date;
                    $update_date = $v->update_date;
               

                 ?>
                    <tr>
                        <td><?php echo str_pad($v->id, 8,'0',STR_PAD_LEFT); ?></td>
                        <td><?= $v->post_title; ?><?php echo strtoupper($v->status)=='COMPLETED'? 
                        sprintf("<br/><a href=\"%s\">%s</a>",get_edit_post_link($v->post_id),__('Edit', 'ai-bot-writer'))
                        :'' ?></td>
                        <td> <?php esc_html_e(strtoupper($v->status), 'ai-bot-writer'); ?> </td>
                        <td data-order="<?= $creation_date=='0000-00-00 00:00:00' ? PHP_INT_MAX : strtotime($creation_date); ?>"><?= $creation_date=='0000-00-00 00:00:00' ? 
                        'N/A' : date(get_option('date_format').' '.get_option('time_format'),
                                        strtotime($creation_date)) ; ?></td>
                        <td data-order="<?= $update_date=='0000-00-00 00:00:00' ? PHP_INT_MAX : strtotime($update_date); ?>"><?= $update_date=='0000-00-00 00:00:00' ? 
                        'N/A' : date(get_option('date_format').' '.get_option('time_format'),
                                        strtotime($update_date)) ; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>    
        </table>
    </div>