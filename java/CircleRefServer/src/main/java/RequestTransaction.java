import com.google.gson.Gson;
import okhttp3.OkHttpClient;
import okhttp3.Response;

import java.io.IOException;

public class RequestTransaction extends Thread {

    private static final Object lock = new Object();

    private FetchResponse transaction;
    private OkHttpClient client;

    public RequestTransaction(FetchResponse transaction) {
        this.transaction = transaction;

        client = new OkHttpClient();
    }

    private void sleep(int millis) {
        try {
            Thread.sleep(millis);
        } catch (InterruptedException e) {
            e.printStackTrace();
        }
    }

    private void checkTransaction() {
        String url = "http://localhost/transaction/" + transaction.getTransaction_id();
        okhttp3.Request req = new okhttp3.Request.Builder().url(url).build();

        try {
            Response response = client.newCall(req).execute();

            Transaction tResponse = new Gson().fromJson(response.body().string(), Transaction.class);
            //System.out.println(tResponse);
            if(tResponse.getError().equals("denied") || tResponse.getError().equals("canceled")) {
                this.
            }


        } catch (IOException e) {
            e.printStackTrace();
        }
    }

    @Override
    public void run() {
        synchronized (lock) {
            checkTransaction();
        }

        this.sleep(5000);
    }
}
