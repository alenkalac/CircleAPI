import com.google.gson.Gson;
import model.FetchResponse;
import model.LoginResponse;
import model.SessionResponse;
import okhttp3.*;

import java.io.IOException;

public class RequestFetcher implements Runnable {

    private int lastID;
    private ProcessRequests pRequsts;
    private static OkHttpClient client;

    public static String token = "";
    public static String userID = "";
    public static String accountID = "";
    public static String auth_token = "";

    public RequestFetcher(ProcessRequests pRequsts) {
        System.out.println("STARTING FETCH SERVICE");
        this.client = new OkHttpClient();

        this.pRequsts = pRequsts;

        if(login())
            init();

        lastID = 0; //API CALL TO GET THE LAST PROCESSED ID.
    }

    public static boolean login() {
        System.out.println("RUNNING LOGIN SEQUENCE");
        try {
            Request request = new Request.Builder()
                    .url("http://localhost/login")
                    .method("POST", RequestBody.create(null, new byte[0]))
                    .addHeader("app-user", "javaserver.admin.user@alenkalac.com")
                    .addHeader("app-pass", "4iRbi0qjBjp5DzzohaQkJSGh5wX4kGoM")
                    .build();
            Response response = client.newCall(request).execute();

            if(response.code() == 200) {
                String data = response.body().string();
                LoginResponse loginRes = new Gson().fromJson(data, LoginResponse.class);

                RequestFetcher.auth_token = loginRes.getAuth_token();
                return true;

            } else {
                System.out.println("WRONG DETAILS");
            }

        }catch(IOException e) {
            login();
        }
        return false;
    }

    private void sleep(int millis) {
        try {
            Thread.sleep(millis);
        } catch (InterruptedException e) {
            e.printStackTrace();
        }
    }

    private static void init() {
        try {
            Request request = new Request.Builder()
                    .header("app-token", RequestFetcher.auth_token)
                    .url("http://localhost/session")
                    .build();
            Response response = client.newCall(request).execute();
            if(response.code() == 401) {
                RequestFetcher.login();
                throw new IOException();
            }

            String data = response.body().string();

            SessionResponse session = new Gson().fromJson(data, SessionResponse.class);

            RequestFetcher.token = session.getToken();
            RequestFetcher.userID = session.getUser();
            RequestFetcher.accountID = session.getAccount();

            System.out.println("SETTING TOKEN TO " + token);

        }catch(IOException e) {
           init();
        }
    }

    private void doFetch() {
        if(auth_token == "") return;
        if(token == "") init();

        RequestBody body = new MultipartBody.Builder()
                .setType(MultipartBody.FORM)
                .addFormDataPart("last_id", String.valueOf(this.lastID))
                .build();

        Request request = new Request.Builder()
                .url("http://localhost/fetch/requests")
                .header("app-token", RequestFetcher.auth_token)
                .post(body)
                .build();

        System.out.println("FETCHING WITH LAST_ID " + this.lastID );

        try {
            Response response = client.newCall(request).execute();

            if(response.code() == 401) {
                login();
                return;
            }

            String data = response.body().string();

            FetchResponse[] res = new Gson().fromJson(data, FetchResponse[].class);

            for(FetchResponse r : res ) {
                System.out.println("FETCHED=== " + r.getTransaction_id());
                int currentID = Integer.parseInt(r.getId());
                if(currentID > this.lastID)
                    this.lastID = currentID;
                pRequsts.addRequest(new RequestTransaction(r));
            }

        }catch(IOException e) {
            doFetch();
        }
    }

    public void run() {
        while(true) {
            doFetch();
            this.sleep(5000);
        }
    }
}
